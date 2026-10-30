<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

enum ConsentStatus: string
{
    case Unknown = 'unknown';
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Obtained = 'obtained';
    case Withdrawn = 'withdrawn';

    /** Material whose consent was withdrawn or never confirmed must not be published. */
    public function permitsPublication(): bool
    {
        return $this === self::Obtained || $this === self::NotRequired;
    }
}
