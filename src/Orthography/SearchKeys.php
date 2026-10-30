<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Orthography;

use Atannex\Lexicon\Enums\MatchSensitivity;

/** The derived forms of one spelling. The original is stored separately and untouched. */
final readonly class SearchKeys
{
    public function __construct(
        public string $exact,
        public string $toneless,
        public string $bare,
        public string $sort,
    ) {}

    public function for(MatchSensitivity $sensitivity): string
    {
        return match ($sensitivity) {
            MatchSensitivity::Exact => $this->exact,
            MatchSensitivity::ToneInsensitive => $this->toneless,
            MatchSensitivity::DiacriticInsensitive => $this->bare,
        };
    }
}
