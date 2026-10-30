<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Search;

use ArrayIterator;
use Atannex\Lexicon\Models\Lexeme;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, SearchHit> */
final readonly class SearchResults implements Countable, IteratorAggregate
{
    /** @param list<SearchHit> $hits best first, one per entry */
    public function __construct(public array $hits, public SearchQuery $query) {}

    /** @return list<Lexeme> */
    public function lexemes(): array
    {
        return array_map(static fn (SearchHit $hit): Lexeme => $hit->lexeme, $this->hits);
    }

    public function first(): ?SearchHit
    {
        return $this->hits[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->hits === [];
    }

    public function count(): int
    {
        return count($this->hits);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->hits);
    }
}
