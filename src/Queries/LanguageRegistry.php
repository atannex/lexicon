<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Queries;

use Atannex\Lexicon\Actions\RegisterLanguage;
use Atannex\Lexicon\Actions\RelateLanguages;
use Atannex\Lexicon\Data\RegisterLanguageData;
use Atannex\Lexicon\Enums\Certainty;
use Atannex\Lexicon\Enums\LanguageRelationshipType;
use Atannex\Lexicon\Exceptions\UnknownLanguage;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\LanguageRelationship;
use Atannex\Lexicon\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Languages visible in the current tenant: its own plus the shared ones. */
final class LanguageRegistry
{
    /** @var array<string, Language> */
    private array $memo = [];

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly RegisterLanguage $register,
        private readonly RelateLanguages $relate,
    ) {}

    /** @return Builder<Language> */
    public function query(): Builder
    {
        return Language::query();
    }

    /** @return Collection<int, Language> */
    public function all(bool $activeOnly = true): Collection
    {
        return Language::query()->when($activeOnly, fn (Builder $q) => $q->where('is_active', true))->orderBy('code')->get();
    }

    public function findByCode(string $code): ?Language
    {
        $key = $this->tenants->current().'|'.$code;

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $language = Language::query()->where('code', $code)->first();

        return $language === null ? null : $this->memo[$key] = $language;
    }

    public function get(string $code): Language
    {
        return $this->findByCode($code) ?? throw UnknownLanguage::code($code);
    }

    /** @return Collection<int, Language> */
    public function findByIso(string $iso639_3): Collection
    {
        return Language::query()->where('iso639_3', $iso639_3)->get();
    }

    public function register(RegisterLanguageData $data): Language
    {
        $this->memo = [];

        return $this->register->handle($data);
    }

    public function relate(
        Language|string $from,
        LanguageRelationshipType $type,
        Language|string $to,
        Certainty $certainty = Certainty::Proposed,
        int|string|null $sourceId = null,
        ?string $notes = null,
    ): LanguageRelationship {
        return $this->relate->handle(
            $from instanceof Language ? $from : $this->get($from),
            $type,
            $to instanceof Language ? $to : $this->get($to),
            $certainty,
            $sourceId,
            $notes,
        );
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
