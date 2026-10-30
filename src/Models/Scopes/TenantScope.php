<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models\Scopes;

use Atannex\Lexicon\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits queries to the current tenant. With $includeShared, records in the
 * shared scope ('') are visible too: that is how reference data such as
 * language identities is shared while dictionary content stays private.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
final readonly class TenantScope implements Scope
{
    public function __construct(private bool $includeShared) {}

    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantContext::class)->current();
        $column = $model->qualifyColumn('tenant');

        if ($this->includeShared && $tenant !== TenantContext::SHARED) {
            $builder->whereIn($column, [TenantContext::SHARED, $tenant]);
        } else {
            $builder->where($column, $tenant);
        }
    }
}
