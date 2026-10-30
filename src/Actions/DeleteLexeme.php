<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Events\LexemeDeleted;
use Atannex\Lexicon\Models\Attestation;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Support\References;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Permanently deletes an entry and everything that belongs to it (forms,
 * senses, definitions, examples, relations, attestations). Sources stay.
 * Prefer archiving (ChangeLexemeStatus to Archived) for anything that was
 * ever published: archived entries keep their history.
 */
final class DeleteLexeme extends Action
{
    public function __construct(private readonly References $references, private readonly Dispatcher $events) {}

    public function handle(Lexeme $lexeme, ?string $actor = null): void
    {
        $this->references->assertOwned($lexeme);

        $event = new LexemeDeleted($lexeme->uid, $lexeme->tenant, $lexeme->language_id, $lexeme->lemmaText(), $actor);

        $this->transaction(function () use ($lexeme): void {
            // Attestations point at their claims polymorphically, so the
            // database cannot cascade them; lexeme_id lets us find them all.
            Attestation::query()->where('lexeme_id', $lexeme->id)->delete();
            $lexeme->lemma()->dissociate()->save();
            $lexeme->delete();
        });

        $this->events->dispatch($event);
    }
}
