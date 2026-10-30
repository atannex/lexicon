<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\CreateLexemeData;
use Atannex\Lexicon\Data\FormData;
use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Enums\FormKind;
use Atannex\Lexicon\Enums\Visibility;
use Atannex\Lexicon\Events\LexemeCreated;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Support\References;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Creates an entry with its lemma, extra forms and senses in one
 * transaction: either the whole entry is stored or nothing is.
 *
 * New entries are never published directly; publication goes through
 * ChangeLexemeStatus, which checks consent on the cited sources.
 */
final class CreateLexeme extends Action
{
    public function __construct(
        private readonly References $references,
        private readonly AddForm $addForm,
        private readonly AddSense $addSense,
        private readonly Attest $attest,
        private readonly Dispatcher $events,
    ) {}

    public function handle(Language $language, CreateLexemeData $data): Lexeme
    {
        $this->references->assertVisible($language);

        $status = $data->status ?? EntryStatus::tryFrom((string) config('lexicon.defaults.status')) ?? EntryStatus::Draft;

        if (! in_array($status, [EntryStatus::Draft, EntryStatus::Submitted], true)) {
            throw IntegrityViolation::make('New entries start as drafts or submissions; publish them with ChangeLexemeStatus.');
        }

        $lexeme = $this->transaction(function () use ($language, $data, $status): Lexeme {
            $source = $this->references->source($data->sourceId);

            $lexeme = new Lexeme([
                'part_of_speech' => $data->partOfSpeech,
                'homograph' => $data->homograph,
                'metadata' => $data->metadata ?: null,
            ]);
            $lexeme->language()->associate($language);
            $lexeme->variety()->associate($this->references->variety($language, $data->variety));
            $lexeme->contributor()->associate($this->references->contributor($data->contributorId));
            $lexeme->status = $status;
            $lexeme->visibility = $data->visibility ?? Visibility::tryFrom((string) config('lexicon.defaults.visibility')) ?? Visibility::Private;
            $lexeme->save();

            $lemma = $this->addForm->store($lexeme, new FormData(
                text: $data->lemma,
                kind: FormKind::Lemma,
                orthography: $data->orthography,
                variety: $data->variety,
            ));

            $lexeme->lemma()->associate($lemma);
            $lexeme->save();

            if ($data->homograph !== null) {
                $this->assertUniqueHomograph($lexeme, $lemma->key_exact);
            }

            if ($source !== null) {
                $this->attest->store($lemma, $source, $data->locator, contributorId: $lexeme->contributor_id);
            }

            foreach ($data->forms as $form) {
                $this->addForm->store($lexeme, $form);
            }

            foreach ($data->senses as $position => $sense) {
                $this->addSense->store($lexeme, $sense, $position);
            }

            return $lexeme;
        });

        $this->events->dispatch(new LexemeCreated($lexeme));

        return $lexeme->load('lemma');
    }

    private function assertUniqueHomograph(Lexeme $lexeme, string $key): void
    {
        $clash = Lexeme::query()
            ->where('language_id', $lexeme->language_id)
            ->where('homograph', $lexeme->homograph)
            ->whereKeyNot($lexeme->id)
            ->whereHas('lemma', fn ($q) => $q->where('key_exact', $key))
            ->exists();

        if ($clash) {
            throw IntegrityViolation::make("Homograph number {$lexeme->homograph} is already used for this spelling.");
        }
    }
}
