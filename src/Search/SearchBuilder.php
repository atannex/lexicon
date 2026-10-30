<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Search;

use Atannex\Lexicon\Authorization\ReadAccess;
use Atannex\Lexicon\Contracts\SearchEngine;
use Atannex\Lexicon\Enums\MatchMode;
use Atannex\Lexicon\Enums\MatchSensitivity;
use Atannex\Lexicon\Enums\SearchField;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Variety;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Fluent search. Defaults: prefix matching on forms, the configured
 * sensitivity, anonymous read access.
 *
 *   Lexicon::language('nweh')->search('ngang')->toneInsensitive()->limit(10)->get();
 */
final class SearchBuilder
{
    private ReadAccess $access;

    private MatchSensitivity $sensitivity;

    private MatchMode $mode = MatchMode::Prefix;

    /** @var list<SearchField> */
    private array $fields = [SearchField::Forms];

    private int $limit;

    private ?int $varietyId = null;

    private ?string $partOfSpeech = null;

    private ?string $definitionLanguage = null;

    public function __construct(
        private readonly SearchEngine $engine,
        private readonly string $term,
        private readonly ?Language $language = null,
    ) {
        $this->access = ReadAccess::public();
        $this->sensitivity = MatchSensitivity::tryFrom((string) config('lexicon.search.sensitivity')) ?? MatchSensitivity::Exact;
        $default = config('lexicon.search.default_limit', 20);
        $this->limit = is_int($default) ? $default : 20;
    }

    public function for(?Authenticatable $user): self
    {
        $this->access = ReadAccess::for($user);

        return $this;
    }

    public function withAccess(ReadAccess $access): self
    {
        $this->access = $access;

        return $this;
    }

    public function sensitivity(MatchSensitivity $sensitivity): self
    {
        $this->sensitivity = $sensitivity;

        return $this;
    }

    public function exact(): self
    {
        return $this->sensitivity(MatchSensitivity::Exact);
    }

    public function toneInsensitive(): self
    {
        return $this->sensitivity(MatchSensitivity::ToneInsensitive);
    }

    public function diacriticInsensitive(): self
    {
        return $this->sensitivity(MatchSensitivity::DiacriticInsensitive);
    }

    public function mode(MatchMode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function whole(): self
    {
        return $this->mode(MatchMode::Whole);
    }

    public function prefix(): self
    {
        return $this->mode(MatchMode::Prefix);
    }

    public function contains(): self
    {
        return $this->mode(MatchMode::Contains);
    }

    public function in(SearchField ...$fields): self
    {
        $this->fields = array_values(array_unique($fields, SORT_REGULAR)) ?: [SearchField::Forms];

        return $this;
    }

    public function everywhere(): self
    {
        return $this->in(...SearchField::cases());
    }

    public function variety(string $code): self
    {
        if ($this->language === null) {
            throw IntegrityViolation::make('Choose a language before filtering by variety.');
        }

        $variety = Variety::query()->where('language_id', $this->language->id)->where('code', $code)->first()
            ?? throw IntegrityViolation::make("Language [{$this->language->code}] has no variety [{$code}].");

        $this->varietyId = $variety->id;

        return $this;
    }

    public function partOfSpeech(?string $partOfSpeech): self
    {
        $this->partOfSpeech = $partOfSpeech;

        return $this;
    }

    /** Only match definitions written in this language. */
    public function definitionsIn(?string $tag): self
    {
        $this->definitionLanguage = $tag;

        return $this;
    }

    public function limit(int $limit): self
    {
        $max = config('lexicon.search.max_limit', 100);
        $this->limit = max(1, min($limit, is_int($max) ? $max : 100));

        return $this;
    }

    public function toQuery(): SearchQuery
    {
        return new SearchQuery(
            term: $this->term,
            access: $this->access,
            languageId: $this->language?->id,
            varietyId: $this->varietyId,
            sensitivity: $this->sensitivity,
            mode: $this->mode,
            fields: $this->fields,
            limit: $this->limit,
            partOfSpeech: $this->partOfSpeech,
            definitionLanguage: $this->definitionLanguage,
        );
    }

    public function get(): SearchResults
    {
        return $this->engine->search($this->toQuery());
    }
}
