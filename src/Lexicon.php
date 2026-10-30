<?php

declare(strict_types=1);

namespace Atannex\Lexicon;

use Atannex\Lexicon\Actions\CreateContributor;
use Atannex\Lexicon\Actions\CreateSource;
use Atannex\Lexicon\Contracts\SearchEngine;
use Atannex\Lexicon\Data\ContributorData;
use Atannex\Lexicon\Data\CreateSourceData;
use Atannex\Lexicon\Extensions\ExtensionRegistry;
use Atannex\Lexicon\Models\Contributor;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Source;
use Atannex\Lexicon\Orthography\OrthographyRegistry;
use Atannex\Lexicon\Queries\LanguageRegistry;
use Atannex\Lexicon\Queries\LanguageScope;
use Atannex\Lexicon\Search\SearchBuilder;
use Atannex\Lexicon\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Container\Container;

/**
 * Entry point behind the Lexicon facade. It only hands out the right
 * scoped object; the work happens in Actions, Queries and the search
 * engine, which can all be resolved from the container directly.
 */
final readonly class Lexicon
{
    public function __construct(private Container $container) {}

    public function languages(): LanguageRegistry
    {
        return $this->container->make(LanguageRegistry::class);
    }

    public function language(string|Language $language): LanguageScope
    {
        return new LanguageScope($language instanceof Language ? $language : $this->languages()->get($language));
    }

    /** Search every language visible to the current tenant. */
    public function search(string $term): SearchBuilder
    {
        return new SearchBuilder($this->container->make(SearchEngine::class), $term);
    }

    public function createSource(CreateSourceData $data): Source
    {
        return $this->container->make(CreateSource::class)->handle($data);
    }

    public function findSource(string $uid): ?Source
    {
        return Source::query()->where('uid', $uid)->first();
    }

    public function createContributor(ContributorData $data): Contributor
    {
        return $this->container->make(CreateContributor::class)->handle($data);
    }

    /**
     * Run $callback as $tenant: for commands and queued jobs, which have
     * no request to resolve a tenant from.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function forTenant(string|int|null $tenant, Closure $callback): mixed
    {
        return $this->container->make(TenantContext::class)->run($tenant, $callback);
    }

    public function tenant(): string
    {
        return $this->container->make(TenantContext::class)->current();
    }

    public function orthographies(): OrthographyRegistry
    {
        return $this->container->make(OrthographyRegistry::class);
    }

    public function extensions(): ExtensionRegistry
    {
        return $this->container->make(ExtensionRegistry::class);
    }

    public function supports(string $capability): bool
    {
        return $this->extensions()->supports($capability);
    }
}
