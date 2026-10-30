<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Enums\RelationType;
use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Relation;
use Atannex\Lexicon\Models\Sense;
use Atannex\Lexicon\Support\References;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Asserts "from → type → to". Only one row is stored; the inverse reading
 * (narrower for broader, has_variant for variant_of) is derived on read.
 * Cross-language equivalence may join entries of different languages;
 * every other relation stays within one language.
 */
final class RelateLexemes extends Action
{
    public function __construct(private readonly References $references, private readonly Dispatcher $events) {}

    public function handle(
        Lexeme $from,
        RelationType $type,
        Lexeme $to,
        ?Sense $fromSense = null,
        ?Sense $toSense = null,
        ?string $notes = null,
        ?string $actor = null,
    ): Relation {
        $this->references->assertOwned($from);
        $this->references->assertOwned($to);

        if ($from->is($to) && ($fromSense === null || $fromSense->is($toSense))) {
            throw IntegrityViolation::make('An entry cannot be related to itself.');
        }

        if ($fromSense !== null && $fromSense->lexeme_id !== $from->id) {
            throw IntegrityViolation::make('The source sense belongs to another entry.');
        }

        if ($toSense !== null && $toSense->lexeme_id !== $to->id) {
            throw IntegrityViolation::make('The target sense belongs to another entry.');
        }

        $crossLanguage = $from->language_id !== $to->language_id;

        if ($crossLanguage !== ($type === RelationType::CrossLanguageEquivalent)) {
            throw IntegrityViolation::make($crossLanguage
                ? 'Entries in different languages can only be related as cross-language equivalents.'
                : 'Cross-language equivalents must be in different languages.');
        }

        if ($this->exists($from, $type, $to, $fromSense, $toSense)) {
            throw IntegrityViolation::make('This relation is already recorded.');
        }

        $relation = new Relation(['type' => $type, 'notes' => $notes]);
        $relation->from()->associate($from);
        $relation->to()->associate($to);
        $relation->fromSense()->associate($fromSense);
        $relation->toSense()->associate($toSense);
        $relation->save();

        $this->events->dispatch(new LexemeUpdated($from, 'relation', $actor));

        return $relation;
    }

    /** The same fact may already be stored from either end. */
    private function exists(Lexeme $from, RelationType $type, Lexeme $to, ?Sense $fromSense, ?Sense $toSense): bool
    {
        $match = static fn ($q, Lexeme $a, ?Sense $as, RelationType $t, Lexeme $b, ?Sense $bs) => $q
            ->where('from_lexeme_id', $a->id)->where('to_lexeme_id', $b->id)->where('type', $t->value)
            ->where('from_sense_id', $as?->id)->where('to_sense_id', $bs?->id);

        return Relation::query()
            ->where(fn ($q) => $match($q, $from, $fromSense, $type, $to, $toSense))
            ->orWhere(fn ($q) => $match($q, $to, $toSense, $type->inverse(), $from, $fromSense))
            ->exists();
    }
}
