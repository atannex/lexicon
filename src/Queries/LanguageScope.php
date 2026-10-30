<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Queries;

use Atannex\Lexicon\Actions\RegisterOrthography;
use Atannex\Lexicon\Actions\RegisterVariety;
use Atannex\Lexicon\Contracts\SearchEngine;
use Atannex\Lexicon\Data\RegisterOrthographyData;
use Atannex\Lexicon\Data\RegisterVarietyData;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Orthography;
use Atannex\Lexicon\Models\Variety;
use Atannex\Lexicon\Search\SearchBuilder;
use Illuminate\Database\Eloquent\Collection;

/** Everything addressed to one language: Lexicon::language('nweh'). */
final readonly class LanguageScope
{
    public function __construct(private Language $language) {}

    public function model(): Language
    {
        return $this->language;
    }

    public function entries(): EntryQuery
    {
        return new EntryQuery($this->language);
    }

    public function search(string $term): SearchBuilder
    {
        return new SearchBuilder(app(SearchEngine::class), $term, $this->language);
    }

    /** @return Collection<int, Variety> */
    public function varieties(): Collection
    {
        return $this->language->varieties()->orderBy('code')->get();
    }

    public function addVariety(RegisterVarietyData $data): Variety
    {
        return app(RegisterVariety::class)->handle($this->language, $data);
    }

    /** @return Collection<int, Orthography> */
    public function orthographies(): Collection
    {
        return $this->language->orthographies()->orderByDesc('is_default')->orderBy('code')->get();
    }

    public function addOrthography(RegisterOrthographyData $data): Orthography
    {
        return app(RegisterOrthography::class)->handle($this->language, $data);
    }
}
