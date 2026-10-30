<?php

declare(strict_types=1);

use Atannex\Lexicon\Exceptions\InvalidText;
use Atannex\Lexicon\Support\Unicode;

it('composes canonically equivalent spellings to the same string', function (): void {
    $decomposed = "Ngang\u{0061}\u{0300}";

    expect($decomposed)->not->toBe('Ngangà')
        ->and(Unicode::nfc($decomposed))->toBe('Ngangà');
});

it('folds case without dropping marks', function (): void {
    expect(Unicode::fold('Njœêlekœr̄'))->toBe('njœêlekœr̄')
        ->and(Unicode::fold('Betaâgnúá'))->toBe('betaâgnúá');
});

it('strips only the listed marks', function (): void {
    expect(Unicode::stripMarks('Betaâgnúá', ["\u{0301}"]))->toBe('Betaâgnua')
        ->and(Unicode::stripMarks('Betaâgnúá'))->toBe('Betaagnua')
        ->and(Unicode::stripMarks('Mbeqlěq', []))->toBe('Mbeqlěq');
});

it('counts grapheme clusters, not code points', function (): void {
    // r + combining macron is one user-perceived character.
    expect(Unicode::graphemes('kœr̄'))->toBe(['k', 'œ', 'r̄'])
        ->and(Unicode::length("Ngang\u{0061}\u{0300}"))->toBe(6)
        ->and(mb_strlen("Ngang\u{0061}\u{0300}"))->toBe(7);
});

it('handles right-to-left and non-Latin scripts', function (): void {
    expect(Unicode::fold('ΆΛΦΑ'))->toBe('άλφα')
        ->and(Unicode::stripMarks('مَكْتَبَة'))->toBe('مكتبة')
        ->and(Unicode::graphemes('हिन्दी'))->toHaveCount(3);
});

it('rejects invalid UTF-8', function (): void {
    Unicode::nfc("\xC3\x28");
})->throws(InvalidText::class);

it('escapes LIKE wildcards', function (): void {
    expect(Unicode::escapeLike('50%_off!'))->toBe('50!%!_off!!');
});
