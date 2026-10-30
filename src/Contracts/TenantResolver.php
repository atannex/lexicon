<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Contracts;

/**
 * Tells the lexicon which tenant the current request or job belongs to.
 * Bind your own implementation to connect any multi-tenancy library.
 */
interface TenantResolver
{
    /** The current tenant's key, or null for the shared scope. */
    public function current(): string|int|null;
}
