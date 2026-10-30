<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Exceptions;

final class UnknownLanguage extends LexiconException
{
    public static function code(string $code): self
    {
        return new self("No language with code [{$code}] is registered in this scope.");
    }
}
