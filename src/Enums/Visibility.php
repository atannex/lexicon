<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

/**
 * Who may read a record, independently of its editorial status.
 *
 * Restricted covers community-controlled knowledge (ceremonial vocabulary,
 * sacred terms) that must never reach the public even when published.
 */
enum Visibility: string
{
    case Public = 'public';
    case Community = 'community';
    case Restricted = 'restricted';
    case Private = 'private';

    /** Gate ability needed to read records with this visibility; null = anyone. */
    public function ability(): ?string
    {
        return match ($this) {
            self::Public => null,
            self::Community => 'lexicon.view-community',
            self::Restricted => 'lexicon.view-restricted',
            self::Private => 'lexicon.view-private',
        };
    }
}
