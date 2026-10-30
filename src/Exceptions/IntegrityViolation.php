<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Exceptions;

/** A write would break a rule of the lexicographic model (wrong language, wrong tenant, ...). */
final class IntegrityViolation extends LexiconException
{
    public static function make(string $message): self
    {
        return new self($message);
    }
}
