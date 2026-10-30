<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Support;

use Atannex\Lexicon\Exceptions\InvalidText;
use Normalizer;

/**
 * Unicode helpers. None of these are applied to the stored original
 * spelling; they derive comparison and search forms from it.
 */
final class Unicode
{
    public static function assertValid(string $text): void
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            throw InvalidText::encoding();
        }
    }

    /** Canonical composition (NFC): canonically equivalent strings become identical. */
    public static function nfc(string $text): string
    {
        self::assertValid($text);

        $normalized = Normalizer::normalize($text, Normalizer::FORM_C);

        if ($normalized === false) {
            throw InvalidText::encoding();
        }

        return $normalized;
    }

    public static function nfd(string $text): string
    {
        self::assertValid($text);

        $normalized = Normalizer::normalize($text, Normalizer::FORM_D);

        if ($normalized === false) {
            throw InvalidText::encoding();
        }

        return $normalized;
    }

    /** Full Unicode case folding, returned in NFC. */
    public static function fold(string $text): string
    {
        return self::nfc(mb_convert_case(self::nfd($text), MB_CASE_FOLD, 'UTF-8'));
    }

    /**
     * Remove combining marks. With $marks = null every nonspacing mark (Mn)
     * goes; otherwise only the listed marks do. Precomposed letters are
     * decomposed first so "à" loses its grave just like "a\u{0300}".
     *
     * @param  list<string>|null  $marks
     */
    public static function stripMarks(string $text, ?array $marks = null): string
    {
        $decomposed = self::nfd($text);

        if ($marks === null) {
            $stripped = preg_replace('/\p{Mn}+/u', '', $decomposed);
        } elseif ($marks === []) {
            $stripped = $decomposed;
        } else {
            $stripped = str_replace($marks, '', $decomposed);
        }

        return self::nfc((string) $stripped);
    }

    /**
     * Extended grapheme clusters, so "r̄" or "ǹ" count as one character.
     *
     * @return list<string>
     */
    public static function graphemes(string $text): array
    {
        self::assertValid($text);

        preg_match_all('/\X/u', $text, $matches);

        return $matches[0];
    }

    public static function length(string $text): int
    {
        return count(self::graphemes($text));
    }

    /** Escape a user term for LIKE ... ESCAPE '!'. */
    public static function escapeLike(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }
}
