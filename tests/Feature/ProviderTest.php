<?php

declare(strict_types=1);

use Atannex\Lexicon\Contracts\SearchEngine;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\LexiconServiceProvider;
use Atannex\Lexicon\Modules\Editorial\EditorialServiceProvider;
use Atannex\Lexicon\Search\DatabaseSearchEngine;
use Illuminate\Support\Facades\Artisan;

it('is discovered through composer.json', function (): void {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toBe([LexiconServiceProvider::class])
        ->and($composer['extra']['laravel']['aliases'])->toBe(['Lexicon' => Lexicon::class]);
});

it('merges its configuration and resolves the facade', function (): void {
    expect(config('lexicon.database.table_prefix'))->toBe('lex_')
        ->and(Lexicon::getFacadeRoot())->toBeInstanceOf(Atannex\Lexicon\Lexicon::class)
        ->and(app(SearchEngine::class))->toBeInstanceOf(DatabaseSearchEngine::class);
});

it('registers only the commands that exist', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('lexicon:install', 'lexicon:doctor', 'lexicon:languages', 'lexicon:stats', 'lexicon:validate', 'lexicon:reindex');
});

it('runs with every optional module disabled', function (): void {
    expect(Lexicon::supports('lexemes'))->toBeTrue()
        ->and(Lexicon::supports('editorial'))->toBeFalse()
        ->and(Lexicon::extensions()->has('api'))->toBeFalse();
});

it('keeps the configuration cacheable', function (): void {
    $config = require __DIR__.'/../../config/lexicon.php';

    array_walk_recursive($config, function (mixed $value): void {
        expect($value)->not->toBeInstanceOf(Closure::class)->and(is_object($value))->toBeFalse();
    });

    expect(var_export($config, true))->toBeString();
});

it('registers a module provider when its config switch is on', function (): void {
    config(['lexicon.modules.editorial' => true]);
    (new LexiconServiceProvider(app()))->register();

    expect(app()->getProviders(EditorialServiceProvider::class))->not->toBeEmpty();
});
