<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Search;

use Atannex\Lexicon\Enums\SearchField;
use Atannex\Lexicon\Models\Lexeme;

/** One matching entry, with what matched and how well. */
final readonly class SearchHit
{
    public function __construct(
        public Lexeme $lexeme,
        public SearchField $field,
        public string $matched,
        public int $score,
    ) {}
}
