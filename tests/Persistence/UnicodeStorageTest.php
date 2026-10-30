<?php

declare(strict_types=1);

use Atannex\Lexicon\Data\FormData;
use Atannex\Lexicon\Data\RegisterOrthographyData;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Form;
use Illuminate\Support\Facades\DB;

dataset('fixtures', ['Ngangà', 'Mbeqlěq', 'Njœêlekœr̄', 'Betaâgnúá', 'Mbeqńkœó']);

it('stores every fixture byte-for-byte', function (string $spelling): void {
    $entry = entry(language(), $spelling);

    $raw = DB::table('lex_forms')->where('id', $entry->lemma_form_id)->value('text');

    expect(bin2hex((string) $raw))->toBe(bin2hex($spelling));
})->with('fixtures');

it('keeps a decomposed original decomposed, yet finds it from the composed spelling', function (): void {
    $language = language();
    $decomposed = "Ngang\u{0061}\u{0300}";
    published($language, $decomposed);

    expect(Form::query()->value('text'))->toBe($decomposed)
        ->and(lemmas(Lexicon::language($language)->search('Ngangà')->whole()->get()))->toBe([$decomposed]);
});

it('distinguishes tone in exact search and ignores it when asked', function (): void {
    $language = language();
    published($language, 'Ngangà');
    published($language, 'Ngangá');
    published($language, 'Nganga');

    $exact = lemmas(Lexicon::language($language)->search('ngangà')->exact()->whole()->get());
    $toneless = lemmas(Lexicon::language($language)->search('nganga')->toneInsensitive()->whole()->get());

    expect($exact)->toBe(['Ngangà'])
        ->and($toneless)->toHaveCount(3)
        ->and($toneless[0])->toBe('Nganga');
});

it('ignores non-tone diacritics only in diacritic-insensitive search', function (): void {
    $language = language();
    published($language, 'Njœêlekœr̄');

    expect(Lexicon::language($language)->search('njœelekœr')->toneInsensitive()->whole()->get()->count())->toBe(1)
        ->and(Lexicon::language($language)->search('njoelekoer')->diacriticInsensitive()->whole()->get()->count())->toBe(0);
});

it('uses an orthography\'s equivalences for loose search', function (): void {
    $language = language();
    Lexicon::language($language)->addOrthography(new RegisterOrthographyData(code: 'std', name: 'Standard', rules: ['equivalences' => ['œ' => 'oe']]));
    published($language, 'Njœêlekœr̄');

    expect(Lexicon::language($language)->search('njoeelekoer')->diacriticInsensitive()->whole()->get()->count())->toBe(1);
});

it('finds forms written in a second orthography with different rules', function (): void {
    $language = language();
    Lexicon::language($language)->addOrthography(new RegisterOrthographyData(code: 'std', name: 'Standard'));
    Lexicon::language($language)->addOrthography(new RegisterOrthographyData(code: 'old', name: 'Mission spelling', rules: ['equivalences' => ['ŋ' => 'ng']]));
    published($language, 'Ngangà', ['forms' => [new FormData('ŋaŋa', orthography: 'old')]]);

    expect(Lexicon::language($language)->search('ngangà')->diacriticInsensitive()->whole()->get()->first()?->matched)->toBe('Ngangà')
        ->and(Lexicon::language($language)->search('nganga')->diacriticInsensitive()->whole()->get())->toHaveCount(1);
});

it('treats LIKE wildcards in a search term literally', function (): void {
    $language = language();
    published($language, 'abc');

    expect(Lexicon::language($language)->search('%')->contains()->get()->isEmpty())->toBeTrue()
        ->and(Lexicon::language($language)->search('_bc')->contains()->get()->isEmpty())->toBeTrue()
        ->and(Lexicon::language($language)->search('bc')->contains()->get()->count())->toBe(1);
});

it('preserves right-to-left text', function (): void {
    $language = language();
    $entry = published($language, 'مَكْتَبَة');

    expect($entry->fresh()?->lemmaText())->toBe('مَكْتَبَة')
        ->and(Lexicon::language($language)->search('مكتبة')->diacriticInsensitive()->whole()->get())->toHaveCount(1);
});
