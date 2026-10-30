<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Orthography;

use Atannex\Lexicon\Exceptions\InvalidOrthography;
use Atannex\Lexicon\Support\Unicode;

/**
 * An orthography described as data, so a community's conventions can be
 * stored with the orthography record instead of written as code.
 *
 * Every field is optional. Nothing here is a fact about any language: the
 * defaults are generic Unicode behaviour, and anything language-specific
 * must come from the people documenting that language.
 */
final readonly class OrthographyRules
{
    /**
     * @param  list<string>|null  $inventory  graphemes the orthography uses (lowercase, NFC); null = unrestricted
     * @param  list<string>  $toneMarks  combining marks that write tone; ignored by tone-insensitive search
     * @param  array<string, string>  $equivalences  substitutions for diacritic-insensitive search, e.g. ["ŋ" => "ng"]
     * @param  list<string>|null  $alphabet  collation order of graphemes (multigraphs allowed); null = Unicode collation
     * @param  array<string, string>  $transliteration  grapheme map for the transliteration scheme; empty = none
     * @param  list<string>  $wordJoiners  punctuation that stays inside a word (apostrophes, hyphens)
     */
    public function __construct(
        public ?array $inventory = null,
        public array $toneMarks = [],
        public array $equivalences = [],
        public ?array $alphabet = null,
        public array $transliteration = [],
        public array $wordJoiners = ["'", '’', 'ʼ', '-'],
    ) {}

    /** @param array<string, mixed> $rules */
    public static function fromArray(array $rules, ?self $defaults = null): self
    {
        $defaults ??= new self;

        $known = ['inventory', 'tone_marks', 'equivalences', 'alphabet', 'transliteration', 'word_joiners'];

        if ($unknown = array_diff(array_keys($rules), $known)) {
            throw InvalidOrthography::rules('unknown keys '.implode(', ', $unknown).'.');
        }

        return new self(
            inventory: array_key_exists('inventory', $rules) ? self::nullableList($rules['inventory'], 'inventory', fold: true) : $defaults->inventory,
            toneMarks: array_key_exists('tone_marks', $rules) ? self::marks($rules['tone_marks']) : $defaults->toneMarks,
            equivalences: array_key_exists('equivalences', $rules) ? self::map($rules['equivalences'], 'equivalences') : $defaults->equivalences,
            alphabet: array_key_exists('alphabet', $rules) ? self::nullableList($rules['alphabet'], 'alphabet', fold: true) : $defaults->alphabet,
            transliteration: array_key_exists('transliteration', $rules) ? self::map($rules['transliteration'], 'transliteration') : $defaults->transliteration,
            wordJoiners: array_key_exists('word_joiners', $rules) ? (self::nullableList($rules['word_joiners'], 'word_joiners') ?? []) : $defaults->wordJoiners,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'inventory' => $this->inventory,
            'tone_marks' => $this->toneMarks,
            'equivalences' => $this->equivalences,
            'alphabet' => $this->alphabet,
            'transliteration' => $this->transliteration,
            'word_joiners' => $this->wordJoiners,
        ];
    }

    /** @return list<string>|null */
    private static function nullableList(mixed $value, string $field, bool $fold = false): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidOrthography::rules("{$field} must be a list of strings.");
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                throw InvalidOrthography::rules("{$field} must contain non-empty strings.");
            }

            $items[] = $fold ? Unicode::fold($item) : Unicode::nfc($item);
        }

        return array_values(array_unique($items));
    }

    /** @return list<string> */
    private static function marks(mixed $value): array
    {
        $marks = self::nullableList($value, 'tone_marks') ?? [];

        foreach ($marks as $mark) {
            if (! preg_match('/^\p{M}$/u', $mark)) {
                throw InvalidOrthography::rules('tone_marks must be single combining marks such as "\u{0301}".');
            }
        }

        return $marks;
    }

    /** @return array<string, string> */
    private static function map(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw InvalidOrthography::rules("{$field} must map strings to strings.");
        }

        $map = [];

        foreach ($value as $from => $to) {
            if (! is_string($from) || $from === '' || ! is_string($to)) {
                throw InvalidOrthography::rules("{$field} must map non-empty strings to strings.");
            }

            $map[Unicode::fold($from)] = Unicode::nfc($to);
        }

        return $map;
    }
}
