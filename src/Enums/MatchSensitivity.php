<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

/**
 * How strictly a search term must match. Every level is computed from the
 * stored original spelling, which is never altered.
 */
enum MatchSensitivity: string
{
    /** Canonically equivalent and case-folded; every mark is significant. */
    case Exact = 'exact';
    /** As Exact, but the orthography's tone marks are ignored. */
    case ToneInsensitive = 'tone_insensitive';
    /** All combining marks and configured equivalences are ignored. */
    case DiacriticInsensitive = 'diacritic_insensitive';

    public function column(): string
    {
        return match ($this) {
            self::Exact => 'key_exact',
            self::ToneInsensitive => 'key_toneless',
            self::DiacriticInsensitive => 'key_bare',
        };
    }

    /** Column holding this key for running text (definitions, examples). */
    public function textColumn(): string
    {
        return match ($this) {
            self::Exact => 'search_exact',
            self::ToneInsensitive => 'search_toneless',
            self::DiacriticInsensitive => 'search_bare',
        };
    }
}
