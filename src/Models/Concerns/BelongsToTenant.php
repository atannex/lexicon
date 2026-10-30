<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models\Concerns;

use Atannex\Lexicon\Models\Scopes\TenantScope;
use Atannex\Lexicon\Tenancy\TenantContext;

/**
 * Content owned by exactly one tenant. Queries only ever see the current
 * tenant's rows; new rows are stamped with the current tenant.
 *
 * @property string $tenant
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope(includeShared: static::sharesAcrossTenants()));

        static::creating(static function (self $model): void {
            $model->tenant ??= app(TenantContext::class)->current();
        });
    }

    /** Reference data (languages, orthographies) may live in the shared scope. */
    protected static function sharesAcrossTenants(): bool
    {
        return false;
    }

    public function isShared(): bool
    {
        return $this->tenant === TenantContext::SHARED;
    }
}
