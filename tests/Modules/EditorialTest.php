<?php

declare(strict_types=1);

use Atannex\Lexicon\Actions\AddForm;
use Atannex\Lexicon\Authorization\Abilities;
use Atannex\Lexicon\Data\FormData;
use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Exceptions\InvalidTransition;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Modules\Editorial\EditorialWorkflow;
use Atannex\Lexicon\Modules\Editorial\Enums\ReviewDecision;
use Atannex\Lexicon\Modules\Editorial\Enums\SubmissionKind;
use Atannex\Lexicon\Modules\Editorial\Enums\SubmissionStatus;
use Atannex\Lexicon\Modules\Editorial\RevisionHistory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    grant([Abilities::CONTRIBUTE], [1, 2]);
    grant([Abilities::REVIEW], [2]);
    $this->workflow = app(EditorialWorkflow::class);
    $this->language = language();
});

it('takes a new word from submission to an approved entry', function (): void {
    $submission = $this->workflow->submit(user(1), $this->language, SubmissionKind::NewEntry, [
        'lemma' => 'Ngangà',
        'visibility' => 'public',
        'senses' => [['definitions' => [['text' => 'test gloss', 'written_in' => 'en']]]],
    ], sourceId: source()->uid, note: 'heard at the market');

    $this->workflow->startReview(user(2), $submission);
    $this->workflow->approve(user(2), $submission, 'checked with a second speaker');

    $entry = Lexeme::query()->findOrFail($submission->result_id);

    expect($submission->status)->toBe(SubmissionStatus::Approved)
        ->and($entry->lemmaText())->toBe('Ngangà')
        ->and($entry->status)->toBe(EntryStatus::Approved)
        ->and($entry->attestations()->count())->toBe(1)
        ->and($submission->reviews->pluck('decision')->all())->toBe([ReviewDecision::Started, ReviewDecision::Approve]);

    // Publication is a separate decision.
    expect(Lexicon::language($this->language)->entries()->find($entry->uid))->toBeNull();
});

it('applies corrections and additions to existing entries', function (): void {
    $entry = published($this->language, 'Mbeqlěq');
    $sense = $entry->senses()->firstOrFail();

    $form = $this->workflow->submit(user(1), $this->language, SubmissionKind::AddForm, ['text' => 'Mbeqleq', 'kind' => 'historical'], $entry);
    $definition = $this->workflow->submit(user(1), $this->language, SubmissionKind::AddDefinition, ['sense_id' => $sense->id, 'text' => 'glose', 'written_in' => 'fr'], $entry);
    $correction = $this->workflow->submit(user(1), $this->language, SubmissionKind::Correction, ['part_of_speech' => 'verb'], $entry);

    foreach ([$form, $definition, $correction] as $submission) {
        $this->workflow->approve(user(2), $submission);
    }

    expect($entry->forms()->pluck('text')->all())->toContain('Mbeqleq')
        ->and($sense->definitions()->where('written_in', 'fr')->value('text'))->toBe('glose')
        ->and($entry->fresh()?->part_of_speech)->toBe('verb');
});

it('checks permissions on the server for every step', function (): void {
    $submission = $this->workflow->submit(user(1), $this->language, SubmissionKind::NewEntry, ['lemma' => 'x']);

    expect(fn () => $this->workflow->submit(user(3), $this->language, SubmissionKind::NewEntry, ['lemma' => 'y']))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->workflow->approve(user(1), $submission))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->workflow->reject(user(3), $submission, 'no'))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->workflow->withdraw(user(2), $submission))->toThrow(AuthorizationException::class);
});

it('does not let reviewers approve their own submissions', function (): void {
    $submission = $this->workflow->submit(user(2), $this->language, SubmissionKind::NewEntry, ['lemma' => 'x']);

    $this->workflow->approve(user(2), $submission);
})->throws(AuthorizationException::class);

it('validates proposals before they reach reviewers', function (): void {
    expect(fn () => $this->workflow->submit(user(1), $this->language, SubmissionKind::NewEntry, ['lemma' => '']))->toThrow(ValidationException::class)
        ->and(fn () => $this->workflow->submit(user(1), $this->language, SubmissionKind::AddForm, ['text' => 'x']))->toThrow(IntegrityViolation::class)
        ->and(fn () => $this->workflow->submit(user(1), $this->language, SubmissionKind::AddDefinition, ['sense_id' => 999, 'text' => 'x', 'written_in' => 'en'], entry($this->language, 'y')))->toThrow(IntegrityViolation::class);
});

it('supports requested changes, resubmission, rejection and withdrawal', function (): void {
    $submission = $this->workflow->submit(user(1), $this->language, SubmissionKind::NewEntry, ['lemma' => 'Ngangà']);

    $this->workflow->requestChanges(user(2), $submission, 'please add a source');
    $this->workflow->resubmit(user(1), $submission, ['lemma' => 'Ngangà', 'part_of_speech' => 'noun']);
    $this->workflow->reject(user(2), $submission, 'duplicate of an existing entry');

    expect($submission->status)->toBe(SubmissionStatus::Rejected)
        ->and(fn () => $this->workflow->approve(user(2), $submission))->toThrow(InvalidTransition::class)
        ->and(fn () => $this->workflow->withdraw(user(1), $submission))->toThrow(IntegrityViolation::class);

    $other = $this->workflow->submit(user(1), $this->language, SubmissionKind::NewEntry, ['lemma' => 'Mbeqlěq']);
    expect($this->workflow->withdraw(user(1), $other)->status)->toBe(SubmissionStatus::Withdrawn);
});

it('records a revision for every change and diffs them', function (): void {
    $entry = entry($this->language, 'Ngangà');
    app(AddForm::class)->handle($entry, new FormData('Nganga'), actor: '2');
    Lexicon::language($this->language)->entries()->publish($entry, actor: '2');

    $history = app(RevisionHistory::class)->of($entry);

    expect($history->pluck('change')->all())->toBe(['created', 'form', 'status:published'])
        ->and($history[1]->actor)->toBe('2')
        ->and(app(RevisionHistory::class)->diff($history[0], $history[1]))->toHaveKey('forms.1.text', [null, 'Nganga']);

    Lexicon::language($this->language)->entries()->delete($entry);

    expect(app(RevisionHistory::class)->of($entry->uid)->last()?->change)->toBe('deleted');
});

it('keeps revisions immutable', function (): void {
    $entry = entry($this->language, 'Ngangà');
    $revision = app(RevisionHistory::class)->of($entry)->firstOrFail();

    $revision->change = 'tampered';

    expect($revision->save())->toBeFalse()
        ->and($revision->fresh()?->change)->toBe('created');
});

it('announces the editorial capability', function (): void {
    expect(Lexicon::supports('editorial.revisions'))->toBeTrue();
});
