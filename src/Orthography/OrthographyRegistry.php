<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Orthography;

use Atannex\Lexicon\Contracts\OrthographyProfile;
use Atannex\Lexicon\Exceptions\InvalidOrthography;
use Atannex\Lexicon\Models\Orthography;
use Atannex\Lexicon\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the profile that governs an orthography:
 *   1. the profile class registered under the orthography's `profile` key,
 *   2. else its stored rules, layered over the configured default rules,
 *   3. else the default profile.
 * A form without an orthography uses its language's default orthography.
 *
 * Profiles are memoised per orthography id for the life of the registry.
 */
final class OrthographyRegistry
{
    /** @var array<string, class-string<OrthographyProfile>|OrthographyProfile> */
    private array $profiles = [];

    /** @var array<int|string, OrthographyProfile> */
    private array $resolved = [];

    private ?RuleBasedOrthography $default = null;

    /** @param array<string, mixed> $defaultRules */
    public function __construct(
        private readonly Container $container,
        private readonly array $defaultRules = [],
    ) {}

    /** @param class-string<OrthographyProfile>|OrthographyProfile $profile */
    public function register(string $key, string|OrthographyProfile $profile): void
    {
        $this->profiles[$key] = $profile;
        $this->resolved = [];
    }

    public function has(string $key): bool
    {
        return isset($this->profiles[$key]);
    }

    public function default(): RuleBasedOrthography
    {
        return $this->default ??= new RuleBasedOrthography(OrthographyRules::fromArray($this->defaultRules));
    }

    public function profile(string $key): OrthographyProfile
    {
        $profile = $this->profiles[$key] ?? throw InvalidOrthography::unknownProfile($key);

        if (is_string($profile)) {
            $instance = $this->container->make($profile);

            if (! $instance instanceof OrthographyProfile) {
                throw InvalidOrthography::unknownProfile($key);
            }

            $this->profiles[$key] = $profile = $instance;
        }

        return $profile;
    }

    public function for(?Orthography $orthography): OrthographyProfile
    {
        if ($orthography === null) {
            return $this->default();
        }

        return $this->resolved[$orthography->id] ??= $this->build($orthography);
    }

    public function forOrthographyId(?int $orthographyId, ?int $languageId = null): OrthographyProfile
    {
        $tenant = $this->container->make(TenantContext::class)->current();
        $cacheKey = $orthographyId ?? ($languageId === null ? null : "language:{$languageId}:{$tenant}");

        if ($cacheKey === null) {
            return $this->default();
        }

        if (isset($this->resolved[$cacheKey])) {
            return $this->resolved[$cacheKey];
        }

        $orthography = $orthographyId !== null
            ? Orthography::query()->withoutGlobalScopes()->find($orthographyId)
            // A tenant's own default orthography wins over a shared one.
            : Orthography::query()->where('language_id', $languageId)->orderByDesc('is_default')->orderByDesc('tenant')->orderBy('id')->first();

        return $this->resolved[$cacheKey] = $this->for($orthography);
    }

    /**
     * Every distinct profile used by a language's orthographies plus the
     * default, so a search term can be keyed the way each form was keyed.
     *
     * @return list<OrthographyProfile>
     */
    public function profilesForLanguage(int $languageId): array
    {
        $default = $this->forOrthographyId(null, $languageId);
        $profiles = [spl_object_id($default) => $default];

        foreach (Orthography::query()->withoutGlobalScopes()->where('language_id', $languageId)->get() as $orthography) {
            $profile = $this->for($orthography);
            $profiles[spl_object_id($profile)] = $profile;
        }

        return array_values($profiles);
    }

    /** Forget memoised profiles after an orthography's rules change. */
    public function flush(): void
    {
        $this->resolved = [];
    }

    private function build(Orthography $orthography): OrthographyProfile
    {
        if ($orthography->profile !== null && $orthography->profile !== '') {
            return $this->profile($orthography->profile);
        }

        if ($orthography->rules === null || $orthography->rules === []) {
            return $this->default();
        }

        return new RuleBasedOrthography(
            OrthographyRules::fromArray($orthography->rules, $this->default()->rules()),
            'orthography:'.$orthography->id,
        );
    }
}
