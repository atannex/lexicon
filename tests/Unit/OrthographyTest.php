<?php

declare(strict_types=1);

use Atannex\Lexicon\Enums\MatchSensitivity;
use Atannex\Lexicon\Exceptions\InvalidOrthography;
use Atannex\Lexicon\Orthography\OrthographyRegistry;
use Atannex\Lexicon\Orthography\OrthographyRules;
use Atannex\Lexicon\Orthography\RuleBasedOrthography;

it('keys spellings at three strictness levels without touching the original', function (): void {
    $profile = app(OrthographyRegistry::class)->default();
    $original = 'Betaâgnúá';
    $keys = $profile->keys($original);

    expect($original)->toBe('Betaâgnúá')
        ->and($keys->exact)->toBe('betaâgnúá')
        ->and($keys->toneless)->toBe('betaagnua')
        ->and($keys->bare)->toBe('betaagnua');
});

it('treats only configured marks as tone', function (): void {
    // Tilde is not in the default tone list, so tone-insensitive keeps it.
    $profile = app(OrthographyRegistry::class)->default();

    expect($profile->searchKey('ñà', MatchSensitivity::ToneInsensitive))->toBe('ña')
        ->and($profile->searchKey('ñà', MatchSensitivity::DiacriticInsensitive))->toBe('na');
});

it('gives canonically equivalent input identical keys', function (): void {
    $profile = new RuleBasedOrthography;

    expect($profile->keys("Ngang\u{0061}\u{0300}"))->toEqual($profile->keys('Ngangà'));
});

it('applies equivalences to diacritic-insensitive keys only', function (): void {
    $profile = new RuleBasedOrthography(OrthographyRules::fromArray(['equivalences' => ['ŋ' => 'ng']]));

    expect($profile->searchKey('ŋgaŋ', MatchSensitivity::DiacriticInsensitive))->toBe('nggang')
        ->and($profile->searchKey('ŋgaŋ', MatchSensitivity::Exact))->toBe('ŋgaŋ');
});

it('sorts by a custom alphabet with multigraphs, tone as a tie-breaker', function (): void {
    $profile = new RuleBasedOrthography(OrthographyRules::fromArray([
        'alphabet' => ['a', 'b', 'gh', 'g', 'n', 'ŋ', 'o'],
        'tone_marks' => ["\u{0300}"],
    ]));

    $words = ['ŋa', 'ga', 'gha', 'na', 'ba', 'bà', 'bb'];
    usort($words, fn (string $a, string $b): int => strcmp($profile->sortKey($a), $profile->sortKey($b)));

    expect($words)->toBe(['ba', 'bà', 'bb', 'gha', 'ga', 'na', 'ŋa']);
});

it('sorts with Unicode collation by default', function (): void {
    $profile = new RuleBasedOrthography;
    $words = ['zebra', 'Ébène', 'apple', 'eagle'];
    usort($words, fn (string $a, string $b): int => strcmp($profile->sortKey($a), $profile->sortKey($b)));

    expect($words)->toBe(['apple', 'eagle', 'Ébène', 'zebra']);
});

it('keeps apostrophes inside words when tokenizing', function (): void {
    $profile = new RuleBasedOrthography;

    expect($profile->tokenize("M'mouck and M'Mock, side by side."))->toBe(["M'mouck", 'and', "M'Mock", 'side', 'by', 'side']);
});

it('reports graphemes outside the inventory, accepting tone on known letters', function (): void {
    $profile = new RuleBasedOrthography(OrthographyRules::fromArray([
        'inventory' => ['a', 'b', 'e', 'g', 'n', 't', 'u', 'm', 'l', 'q', 'œ'],
        'tone_marks' => ["\u{0300}", "\u{0301}", "\u{0302}", "\u{030C}"],
    ]));

    expect($profile->violations('Betaâgnúá'))->toBe([])
        ->and($profile->violations('Mbeqlěq'))->toBe([])
        ->and($profile->violations('Njœêlekœr̄'))->toBe(['j', 'k', 'r̄']);
});

it('transliterates only when a scheme is defined', function (): void {
    expect((new RuleBasedOrthography)->transliterate('ŋa'))->toBeNull()
        ->and((new RuleBasedOrthography(OrthographyRules::fromArray(['transliteration' => ['ŋ' => 'ng', 'œ' => 'oe']])))->transliterate('Ŋœ'))->toBe('ngoe');
});

it('rejects malformed rules', function (array $rules): void {
    OrthographyRules::fromArray($rules);
})->with([
    'unknown key' => [['letters' => ['a']]],
    'tone mark not a mark' => [['tone_marks' => ['a']]],
    'inventory not a list' => [['inventory' => 'abc']],
    'empty grapheme' => [['alphabet' => ['a', '']]],
])->throws(InvalidOrthography::class);
