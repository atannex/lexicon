<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Exceptions;

final class DuplicateRecord extends LexiconException
{
    public static function languageCode(string $code): self
    {
        return new self("A language with code [{$code}] already exists in this scope.");
    }

    public static function varietyCode(string $code, string $language): self
    {
        return new self("Language [{$language}] already has a variety with code [{$code}].");
    }

    public static function orthographyCode(string $code): self
    {
        return new self("An orthography with code [{$code}] already exists for this language.");
    }
}
