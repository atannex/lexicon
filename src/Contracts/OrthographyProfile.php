<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Contracts;

use Atannex\Lexicon\Enums\MatchSensitivity;
use Atannex\Lexicon\Orthography\SearchKeys;

/**
 * Language-specific text behaviour: how spellings compare, sort, split into
 * words and transliterate. A profile never alters the stored original; it
 * only derives keys from it. Implement this, or describe the rules as data
 * (see RuleBasedOrthography), when a writing system needs more than the
 * neutral Unicode defaults.
 */
interface OrthographyProfile
{
    public function key(): string;

    /** Every derived key for one spelling, computed together. */
    public function keys(string $text): SearchKeys;

    public function searchKey(string $text, MatchSensitivity $sensitivity): string;

    /** A byte-comparable string; ordering two keys orders the spellings. */
    public function sortKey(string $text): string;

    /** @return list<string> */
    public function tokenize(string $text): array;

    /** The spelling in this profile's transliteration scheme, or null when it has none. */
    public function transliterate(string $text): ?string;

    /**
     * Graphemes in $text that the orthography's inventory does not contain.
     * Empty when the text is valid or the profile has no inventory.
     *
     * @return list<string>
     */
    public function violations(string $text): array;
}
