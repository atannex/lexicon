<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Contracts;

use Atannex\Lexicon\Search\SearchQuery;
use Atannex\Lexicon\Search\SearchResults;

/**
 * Finds entries. Implementations must honour the query's tenant (the
 * current TenantContext), its ReadAccess and its language filter: results
 * a reader may not see must never be returned, not even as counts.
 */
interface SearchEngine
{
    public function search(SearchQuery $query): SearchResults;
}
