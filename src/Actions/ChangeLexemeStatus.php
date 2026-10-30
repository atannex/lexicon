<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Events\LexemeStatusChanged;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Exceptions\InvalidTransition;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Source;
use Atannex\Lexicon\Support\References;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Moves an entry through the editorial states (EntryStatus::transitions()).
 * Publishing requires a lemma and at least one sense, and refuses entries
 * that cite a source without consent for publication.
 */
final class ChangeLexemeStatus extends Action
{
    public function __construct(private readonly References $references, private readonly Dispatcher $events) {}

    public function handle(Lexeme $lexeme, EntryStatus $to, ?string $actor = null): Lexeme
    {
        $this->references->assertOwned($lexeme);

        $from = $lexeme->status;

        if ($from === $to) {
            return $lexeme;
        }

        if (! $from->canTransitionTo($to)) {
            throw InvalidTransition::between($from, $to);
        }

        if ($to === EntryStatus::Published) {
            $this->assertPublishable($lexeme);
        }

        $lexeme->status = $to;
        $lexeme->published_at = $to === EntryStatus::Published ? now() : $lexeme->published_at;
        $lexeme->save();

        $this->events->dispatch(new LexemeStatusChanged($lexeme, $from, $to, $actor));

        return $lexeme;
    }

    private function assertPublishable(Lexeme $lexeme): void
    {
        if ($lexeme->lemma_form_id === null) {
            throw IntegrityViolation::make('An entry needs a lemma before it is published.');
        }

        if (! $lexeme->senses()->exists()) {
            throw IntegrityViolation::make('An entry needs at least one sense before it is published.');
        }

        $sources = Source::query()->whereIn('id', $lexeme->attestations()->select('source_id'))->get();

        foreach ($sources as $source) {
            if (! $source->consent->permitsPublication()) {
                throw InvalidTransition::consent();
            }
        }
    }
}
