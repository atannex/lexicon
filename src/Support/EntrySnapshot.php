<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Support;

use Atannex\Lexicon\Models\Definition;
use Atannex\Lexicon\Models\Example;
use Atannex\Lexicon\Models\ExampleTranslation;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Sense;
use Atannex\Lexicon\Models\TranslationEquivalent;

/**
 * A complete, storage-independent picture of one entry: what revisions
 * record and what exports write. Originals appear exactly as stored;
 * derived keys are left out because they can always be recomputed.
 */
final class EntrySnapshot
{
    /** Relations to eager-load before calling of() on many entries. */
    public const array RELATIONS = [
        'language:id,code', 'variety:id,code', 'lemma',
        'forms.orthography:id,code', 'forms.variety:id,code',
        'senses.definitions', 'senses.examples.translations', 'senses.equivalents',
    ];

    /** @return array<string, mixed> */
    public static function of(Lexeme $lexeme): array
    {
        $lexeme->loadMissing(self::RELATIONS);

        return [
            'uid' => $lexeme->uid,
            'language' => $lexeme->language->code,
            'variety' => $lexeme->variety?->code,
            'lemma' => $lexeme->lemma?->text,
            'part_of_speech' => $lexeme->part_of_speech,
            'homograph' => $lexeme->homograph,
            'status' => $lexeme->status->value,
            'visibility' => $lexeme->visibility->value,
            'metadata' => $lexeme->metadata,
            'forms' => $lexeme->forms->map(static fn (Form $f): array => [
                'text' => $f->text,
                'kind' => $f->kind->value,
                'orthography' => $f->orthography?->code,
                'variety' => $f->variety?->code,
                'features' => $f->features,
            ])->values()->all(),
            'senses' => $lexeme->senses->map(static fn (Sense $s): array => [
                'semantic_domain' => $s->semantic_domain,
                'labels' => $s->labels,
                'usage_note' => $s->usage_note,
                'definitions' => $s->definitions->map(static fn (Definition $d): array => ['text' => $d->text, 'written_in' => $d->written_in])->values()->all(),
                'examples' => $s->examples->map(static fn (Example $e): array => [
                    'text' => $e->text,
                    'context' => $e->context,
                    'translations' => $e->translations->mapWithKeys(static fn (ExampleTranslation $t): array => [$t->written_in => $t->text])->all(),
                ])->values()->all(),
                'equivalents' => $s->equivalents->map(static fn (TranslationEquivalent $t): array => [
                    'written_in' => $t->written_in,
                    'text' => $t->text,
                    'type' => $t->type->value,
                    'notes' => $t->notes,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
