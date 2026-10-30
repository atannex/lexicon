<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Tenancy;

use Atannex\Lexicon\Contracts\TenantResolver;

/** Single-tenant installations: everything lives in the shared scope. */
final class NullTenantResolver implements TenantResolver
{
    public function current(): null
    {
        return null;
    }
}
