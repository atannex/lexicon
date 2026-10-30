<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Enums\FormKind;
use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Support\References;
use Illuminate\Contracts\Events\Dispatcher;

/** Makes another form the citation form; the old lemma becomes a variant. */
final class SetLemma extends Action
{
    public function __construct(private readonly References $references, private readonly Dispatcher $events) {}

    public function handle(Lexeme $lexeme, Form $form, ?string $actor = null): Lexeme
    {
        $this->references->assertOwned($lexeme);

        if ($form->lexeme_id !== $lexeme->id) {
            throw IntegrityViolation::make('The form belongs to another entry.');
        }

        $this->transaction(function () use ($lexeme, $form): void {
            $lexeme->forms()->where('kind', FormKind::Lemma->value)->whereKeyNot($form->id)->update(['kind' => FormKind::Variant->value]);
            $form->kind = FormKind::Lemma;
            $form->save();
            $lexeme->lemma()->associate($form)->save();
        });

        $this->events->dispatch(new LexemeUpdated($lexeme, 'lemma', $actor));

        return $lexeme->load('lemma');
    }
}
