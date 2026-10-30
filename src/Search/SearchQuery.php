<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Search;

use Atannex\Lexicon\Authorization\ReadAccess;
use Atannex\Lexicon\Enums\MatchMode;
use Atannex\Lexicon\Enums\MatchSensitivity;
use Atannex\Lexicon\Enums\SearchField;

final readonly class SearchQuery
{
    /** @param list<SearchField> $fields */
    public function __construct(
        public string $term,
        public ReadAccess $access,
        public ?int $languageId = null,
        public ?int $varietyId = null,
        public MatchSensitivity $sensitivity = MatchSensitivity::Exact,
        public MatchMode $mode = MatchMode::Prefix,
        public array $fields = [SearchField::Forms],
        public int $limit = 20,
        public ?string $partOfSpeech = null,
        public ?string $definitionLanguage = null,
    ) {}
}
