<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Exceptions;

use Atannex\Lexicon\Enums\EntryStatus;

final class InvalidTransition extends LexiconException
{
    public static function between(EntryStatus $from, EntryStatus $to): self
    {
        return new self("An entry cannot move from [{$from->value}] to [{$to->value}].");
    }

    public static function consent(): self
    {
        return new self('An entry cannot be published while a source it relies on lacks consent for publication.');
    }
}
