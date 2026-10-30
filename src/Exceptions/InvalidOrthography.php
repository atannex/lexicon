<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Exceptions;

final class InvalidOrthography extends LexiconException
{
    public static function rules(string $reason): self
    {
        return new self("Invalid orthography rules: {$reason}");
    }

    public static function unknownProfile(string $key): self
    {
        return new self("No orthography profile is registered under [{$key}].");
    }
}
