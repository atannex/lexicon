<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Editorial;

use Atannex\Lexicon\Actions\AddDefinition;
use Atannex\Lexicon\Actions\AddForm;
use Atannex\Lexicon\Actions\AddSense;
use Atannex\Lexicon\Actions\ChangeLexemeStatus;
use Atannex\Lexicon\Actions\CreateLexeme;
use Atannex\Lexicon\Actions\UpdateLexeme;
use Atannex\Lexicon\Authorization\Abilities;
use Atannex\Lexicon\Data\CreateLexemeData;
use Atannex\Lexicon\Data\DefinitionData;
use Atannex\Lexicon\Data\FormData;
use Atannex\Lexicon\Data\SenseData;
use Atannex\Lexicon\Data\UpdateLexemeData;
use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Exceptions\InvalidTransition;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Sense;
use Atannex\Lexicon\Modules\Editorial\Enums\ReviewDecision;
use Atannex\Lexicon\Modules\Editorial\Enums\SubmissionKind;
use Atannex\Lexicon\Modules\Editorial\Enums\SubmissionStatus;
use Atannex\Lexicon\Modules\Editorial\Models\Submission;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Support\Tables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Community contributions: propose → review → approve (applied) or
 * reject. Every step checks the actor's abilities on the server and is
 * recorded as a Review row. Approving applies the change through the same
 * core actions every other client uses, inside one transaction, so a
 * submission is never half-applied.
 *
 * Approved new entries land in EntryStatus::Approved; publishing them is
 * a separate decision for someone with lexicon.publish.
 */
final readonly class EditorialWorkflow
{
    public function __construct(private Gate $gate, private References $references) {}

    /** @param array<string, mixed> $payload input for the kind's Data class */
    public function submit(
        Authenticatable $user,
        Language $language,
        SubmissionKind $kind,
        array $payload,
        ?Lexeme $target = null,
        int|string|null $sourceId = null,
        ?string $note = null,
    ): Submission {
        $this->authorize($user, Abilities::CONTRIBUTE);
        $this->references->assertVisible($language);

        if ($kind->needsTarget() !== ($target !== null)) {
            throw IntegrityViolation::make($kind->needsTarget() ? 'This kind of submission needs a target entry.' : 'A new entry has no target.');
        }

        if ($target !== null) {
            $this->references->assertOwned($target);

            if ($target->language_id !== $language->id) {
                throw IntegrityViolation::make('The target entry is in another language.');
            }
        }

        // Validate now, so reviewers only ever see well-formed proposals.
        $this->parse($kind, $payload);

        if ($kind === SubmissionKind::AddDefinition) {
            $this->targetSense($target, $payload);
        }

        $submission = new Submission([
            'kind' => $kind,
            'payload' => $payload,
            'note' => $note,
            'source_id' => $this->references->source($sourceId)?->id,
        ]);
        $submission->language()->associate($language);
        $submission->lexeme()->associate($target);
        $submission->status = SubmissionStatus::Submitted;
        $submission->submitted_by = self::id($user);
        $submission->save();

        return $submission;
    }

    public function startReview(Authenticatable $user, Submission $submission): Submission
    {
        $this->authorize($user, Abilities::REVIEW);
        $this->expect($submission, [SubmissionStatus::Submitted]);

        return $this->move($submission, SubmissionStatus::UnderReview, $user, ReviewDecision::Started);
    }

    public function comment(Authenticatable $user, Submission $submission, string $comment): Submission
    {
        $this->authorize($user, Abilities::REVIEW);
        $this->expect($submission, [SubmissionStatus::Submitted, SubmissionStatus::UnderReview, SubmissionStatus::ChangesRequested]);

        $submission->reviews()->create(['reviewer' => self::id($user), 'decision' => ReviewDecision::Comment, 'comment' => $comment]);

        return $submission;
    }

    public function requestChanges(Authenticatable $user, Submission $submission, string $comment): Submission
    {
        $this->authorize($user, Abilities::REVIEW);
        $this->expect($submission, [SubmissionStatus::Submitted, SubmissionStatus::UnderReview]);

        return $this->move($submission, SubmissionStatus::ChangesRequested, $user, ReviewDecision::RequestChanges, $comment);
    }

    /** @param array<string, mixed> $payload */
    public function resubmit(Authenticatable $user, Submission $submission, array $payload, ?string $note = null): Submission
    {
        $this->expectSubmitter($user, $submission);
        $this->expect($submission, [SubmissionStatus::ChangesRequested]);
        $this->parse($submission->kind, $payload);

        $submission->payload = $payload;
        $submission->note = $note ?? $submission->note;

        return $this->move($submission, SubmissionStatus::Submitted, $user, ReviewDecision::Resubmitted);
    }

    public function approve(Authenticatable $user, Submission $submission, ?string $comment = null): Submission
    {
        $this->authorize($user, Abilities::REVIEW);
        $this->expect($submission, [SubmissionStatus::Submitted, SubmissionStatus::UnderReview]);

        if (! config('lexicon.editorial.allow_self_review', false) && $submission->submitted_by === self::id($user)) {
            throw new AuthorizationException('A submission must be approved by someone other than its author.');
        }

        return DB::connection(Tables::connection())->transaction(function () use ($user, $submission, $comment): Submission {
            $submission->result_id = $this->apply($submission, self::id($user));
            $submission->decided_by = self::id($user);
            $submission->decided_at = now();

            return $this->move($submission, SubmissionStatus::Approved, $user, ReviewDecision::Approve, $comment);
        });
    }

    public function reject(Authenticatable $user, Submission $submission, string $reason): Submission
    {
        $this->authorize($user, Abilities::REVIEW);
        $this->expect($submission, [SubmissionStatus::Submitted, SubmissionStatus::UnderReview, SubmissionStatus::ChangesRequested]);

        $submission->decided_by = self::id($user);
        $submission->decided_at = now();

        return $this->move($submission, SubmissionStatus::Rejected, $user, ReviewDecision::Reject, $reason);
    }

    public function withdraw(Authenticatable $user, Submission $submission): Submission
    {
        $this->expectSubmitter($user, $submission);

        if (! $submission->status->isOpen()) {
            throw IntegrityViolation::make('Only open submissions can be withdrawn.');
        }

        return $this->move($submission, SubmissionStatus::Withdrawn, $user, ReviewDecision::Withdraw);
    }

    /** Applies the proposal and returns the id of the record it created or changed. */
    private function apply(Submission $submission, string $actor): int
    {
        $payload = $submission->payload;
        $target = $submission->lexeme;

        if ($submission->kind->needsTarget() && $target === null) {
            throw IntegrityViolation::make('The entry this submission targets no longer exists.');
        }

        return match ($submission->kind) {
            SubmissionKind::NewEntry => $this->createEntry($submission, $actor),
            SubmissionKind::AddForm => app(AddForm::class)->handle($this->owned($target), FormData::fromArray($payload), $actor)->id,
            SubmissionKind::AddSense => app(AddSense::class)->handle($this->owned($target), SenseData::fromArray($payload), $actor)->id,
            SubmissionKind::AddDefinition => app(AddDefinition::class)->handle($this->targetSense($target, $payload), DefinitionData::fromArray($payload), $actor)->id,
            SubmissionKind::Correction => app(UpdateLexeme::class)->handle($this->owned($target), UpdateLexemeData::fromArray($payload), $actor)->id,
        };
    }

    private function createEntry(Submission $submission, string $actor): int
    {
        $data = CreateLexemeData::fromArray([...$submission->payload, 'status' => EntryStatus::Submitted->value]);
        $data = new CreateLexemeData(...[...get_object_vars($data), 'sourceId' => $data->sourceId ?? $submission->source_id]);

        $lexeme = app(CreateLexeme::class)->handle($submission->language, $data);
        $status = app(ChangeLexemeStatus::class);
        $status->handle($lexeme, EntryStatus::UnderReview, $actor);
        $status->handle($lexeme, EntryStatus::Approved, $actor);

        return $lexeme->id;
    }

    private function owned(?Lexeme $lexeme): Lexeme
    {
        if ($lexeme === null) {
            throw IntegrityViolation::make('The entry this submission targets no longer exists.');
        }

        $this->references->assertOwned($lexeme);

        return $lexeme;
    }

    /** @param array<string, mixed> $payload */
    private function targetSense(?Lexeme $target, array $payload): Sense
    {
        $senseId = $payload['sense_id'] ?? null;

        return $this->owned($target)->senses()->whereKey(is_numeric($senseId) ? (int) $senseId : 0)->first()
            ?? throw IntegrityViolation::make('A definition must name a sense (sense_id) of the target entry.');
    }

    /** @param array<string, mixed> $payload */
    private function parse(SubmissionKind $kind, array $payload): void
    {
        $class = $kind->dataClass();
        unset($payload['sense_id']);

        $class::fromArray($payload);
    }

    /** @param list<SubmissionStatus> $allowed */
    private function expect(Submission $submission, array $allowed): void
    {
        if (! in_array($submission->status, $allowed, true)) {
            throw new InvalidTransition("A submission in [{$submission->status->value}] cannot do that.");
        }
    }

    private function expectSubmitter(Authenticatable $user, Submission $submission): void
    {
        if ($submission->submitted_by !== self::id($user)) {
            throw new AuthorizationException('Only the author can change this submission.');
        }
    }

    private function move(Submission $submission, SubmissionStatus $to, Authenticatable $user, ReviewDecision $decision, ?string $comment = null): Submission
    {
        $submission->status = $to;
        $submission->save();
        $submission->reviews()->create(['reviewer' => self::id($user), 'decision' => $decision, 'comment' => $comment]);

        return $submission;
    }

    private function authorize(Authenticatable $user, string $ability): void
    {
        if (! $this->gate->forUser($user)->allows($ability)) {
            throw new AuthorizationException("This action requires [{$ability}].");
        }
    }

    private static function id(Authenticatable $user): string
    {
        $id = $user->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : '';
    }
}
