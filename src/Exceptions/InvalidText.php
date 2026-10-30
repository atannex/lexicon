<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Exceptions;

final class InvalidText extends LexiconException
{
    public static function encoding(): self
    {
        return new self('Text is not valid UTF-8.');
    }

    public static function empty(string $field): self
    {
        return new self("The {$field} cannot be empty.");
    }

    public static function tooLong(string $field, int $max): self
    {
        return new self("The {$field} is longer than {$max} characters.");
    }
}
