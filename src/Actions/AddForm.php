<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\FormData;
use Atannex\Lexicon\Enums\FormKind;
use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Support\Text;
use Illuminate\Contracts\Events\Dispatcher;

/** Adds a spelling or transcription. The text is stored exactly as given. */
final class AddForm extends Action
{
    public function __construct(
        private readonly References $references,
        private readonly Attest $attest,
        private readonly Dispatcher $events,
    ) {}

    public function handle(Lexeme $lexeme, FormData $data, ?string $actor = null): Form
    {
        if ($data->kind === FormKind::Lemma) {
            throw IntegrityViolation::make('An entry has one lemma; add the form as a variant and use SetLemma to promote it.');
        }

        $form = $this->transaction(fn (): Form => $this->store($lexeme, $data));

        $this->events->dispatch(new LexemeUpdated($lexeme, 'form', $actor));

        return $form;
    }

    /** @internal used inside CreateLexeme's transaction */
    public function store(Lexeme $lexeme, FormData $data): Form
    {
        $this->references->assertOwned($lexeme);

        $language = $lexeme->language;

        $form = new Form([
            'kind' => $data->kind,
            'text' => Text::require($data->text, 'form', FormData::MAX_LENGTH),
            'features' => $data->features ?: null,
            'position' => (int) $lexeme->forms()->max('position') + ($lexeme->forms()->exists() ? 1 : 0),
        ]);
        $form->lexeme()->associate($lexeme);
        $form->tenant = $lexeme->tenant;
        $form->language_id = $lexeme->language_id;
        $form->orthography()->associate($this->references->orthography($language, $data->orthography));
        $form->variety()->associate($this->references->variety($language, $data->variety));
        $form->save();

        if ($source = $this->references->source($data->sourceId)) {
            $this->attest->store($form, $source);
        }

        return $form;
    }
}
