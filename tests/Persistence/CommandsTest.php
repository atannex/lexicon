<?php

declare(strict_types=1);

use Atannex\Lexicon\Data\RegisterOrthographyData;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Orthography;

it('registers the four Lebialem identities separately, names only', function (): void {
    $this->artisan('lexicon:install', ['--lebialem' => true])->assertSuccessful();
    $this->artisan('lexicon:install', ['--lebialem' => true])->expectsOutputToContain('already registered')->assertSuccessful();

    $languages = Language::query()->orderBy('code')->get();

    expect($languages->pluck('name', 'code')->all())->toBe(['mmock' => "M'Mock", 'mmouck' => "M'mouck", 'mundani' => 'Mundani', 'nweh' => 'Nweh'])
        ->and($languages->pluck('iso639_3')->filter()->all())->toBe([])
        ->and($languages->every->isShared())->toBeTrue();
});

it('lists languages and statistics', function (): void {
    published(language('testlang'), 'Ngangà');

    $this->artisan('lexicon:languages')->expectsOutputToContain('testlang')->assertSuccessful();
    $this->artisan('lexicon:stats', ['language' => 'testlang'])->expectsOutputToContain('testlang')->assertSuccessful();
    $this->artisan('lexicon:stats', ['language' => 'missing'])->assertFailed();
});

it('passes the doctor on a working install', function (): void {
    $this->artisan('lexicon:doctor')->expectsOutputToContain('All checks passed')->assertSuccessful();
});

it('validates spellings against the orthography inventory', function (): void {
    $language = language('testlang');
    Lexicon::language($language)->addOrthography(new RegisterOrthographyData(code: 'std', name: 'Standard', rules: ['inventory' => ['a', 'b']]));
    entry($language, 'abc');

    $this->artisan('lexicon:validate', ['language' => 'testlang'])->expectsOutputToContain('inventory')->assertFailed();
});

it('reindexes keys after orthography rules change, never touching originals', function (): void {
    $language = language('testlang');
    $orthography = Lexicon::language($language)->addOrthography(new RegisterOrthographyData(code: 'std', name: 'Standard'));
    published($language, 'Njœêlekœr̄');

    Orthography::query()->whereKey($orthography->id)->update(['rules' => json_encode(['equivalences' => ['œ' => 'oe']])]);
    Lexicon::orthographies()->flush();

    $this->artisan('lexicon:validate')->expectsOutputToContain('stale key')->assertFailed();
    $this->artisan('lexicon:reindex', ['--dry-run' => true])->expectsOutputToContain('stale')->assertSuccessful();
    expect(Form::query()->value('key_bare'))->toBe('njœelekœr');

    $this->artisan('lexicon:reindex')->assertSuccessful();

    expect(Form::query()->value('key_bare'))->toBe('njoeelekoer')
        ->and(Form::query()->value('text'))->toBe('Njœêlekœr̄');
});

it('runs commands as a given tenant', function (): void {
    $shared = language('shared', shared: true);
    Lexicon::forTenant('a', fn () => published($shared, 'Ngangà'));

    $this->artisan('lexicon:stats', ['--tenant' => 'a'])->expectsOutputToContain('shared')->assertSuccessful();
});
