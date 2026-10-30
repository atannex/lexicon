<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Support;

use Atannex\Lexicon\Exceptions\InvalidText;

/** Guards for text written to the lexicon. The text itself is never changed. */
final class Text
{
    public static function require(string $text, string $field, int $max = 65535): string
    {
        Unicode::assertValid($text);

        if (trim($text) === '') {
            throw InvalidText::empty($field);
        }

        if (mb_strlen($text) > $max) {
            throw InvalidText::tooLong($field, $max);
        }

        return $text;
    }

    public static function optional(?string $text, string $field, int $max = 65535): ?string
    {
        return $text === null || trim($text) === '' ? null : self::require($text, $field, $max);
    }
}
