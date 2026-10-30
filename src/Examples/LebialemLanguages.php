<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Examples;

use Atannex\Lexicon\Data\RegisterLanguageData;
use Atannex\Lexicon\Enums\DocumentationStatus;

/**
 * The four speech varieties of Lebialem Division (South-West Region,
 * Cameroon) this package was first built for, as separate identities.
 *
 * Only names are recorded. ISO 639-3 codes, varieties, relationships and
 * orthography rules are left empty on purpose: add them once the
 * community and its linguists have confirmed them. M'mouck and M'Mock are
 * distinct records; nothing here asserts how they relate.
 */
final class LebialemLanguages
{
    /** @return list<RegisterLanguageData> */
    public static function definitions(): array
    {
        $geography = ['country' => 'CM', 'region' => 'South-West', 'division' => 'Lebialem'];

        return array_map(
            static fn (array $l): RegisterLanguageData => new RegisterLanguageData(
                code: $l[0],
                name: $l[1],
                geography: $geography,
                documentationStatus: DocumentationStatus::InProgress,
                metadata: ['registered_by' => 'lexicon:install --lebialem'],
                shared: true,
            ),
            [['nweh', 'Nweh'], ['mundani', 'Mundani'], ['mmouck', "M'mouck"], ['mmock', "M'Mock"]],
        );
    }
}
