<?php

declare(strict_types=1);
use Atannex\Lexicon\Search\DatabaseSearchEngine;

return [

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | connection: null uses the application's default connection.
    | table_prefix: every lexicon table is named "<prefix><table>". Change it
    | before running the migrations; renaming afterwards is not automatic.
    | migrations: load the package migrations automatically. Set to false if
    | you publish them with `vendor:publish --tag=lexicon-migrations`.
    |
    */

    'database' => [
        'connection' => env('LEXICON_DB_CONNECTION'),
        'table_prefix' => 'lex_',
        'migrations' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    |
    | A class implementing Atannex\Lexicon\Contracts\TenantResolver. Null runs
    | single-tenant: everything lives in the shared scope (tenant key '').
    | With a resolver, languages, varieties and orthographies may be shared
    | (tenant '') or tenant-owned, while dictionary content always belongs
    | to exactly one tenant.
    |
    */

    'tenant_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Defaults for new records
    |--------------------------------------------------------------------------
    |
    | status / visibility: applied when a write does not name one. Records
    | start as private drafts so nothing is published by accident.
    |
    */

    'defaults' => [
        'status' => 'draft',
        'visibility' => 'private',
    ],

    /*
    |--------------------------------------------------------------------------
    | Orthography
    |--------------------------------------------------------------------------
    |
    | rules: the fallback rules for orthographies that define none. The tone
    | marks below are the combining accents commonly used to write tone
    | (grave, acute, circumflex, macron, caron, double acute, double grave).
    | That is a generic convention, not a description of any language: give
    | each orthography its own rules when its community writes tone
    | differently.
    |
    | profiles: profile key => class implementing OrthographyProfile, for
    | writing systems that need code rather than data.
    |
    */

    'orthography' => [
        'rules' => [
            'tone_marks' => ["\u{0300}", "\u{0301}", "\u{0302}", "\u{0304}", "\u{030C}", "\u{030B}", "\u{030F}"],
        ],
        'profiles' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | engine: a class implementing Atannex\Lexicon\Contracts\SearchEngine.
    | sensitivity: exact | tone_insensitive | diacritic_insensitive.
    | default_limit / max_limit: result bounds; requests above max are capped.
    |
    */

    'search' => [
        'engine' => DatabaseSearchEngine::class,
        'sensitivity' => 'exact',
        'default_limit' => 20,
        'max_limit' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | Optional parts of the package. Each one registers its own migrations,
    | routes and commands only when enabled; the core works with all of
    | them off.
    |
    */

    'modules' => [
        'editorial' => (bool) env('LEXICON_EDITORIAL', false),
        'exchange' => (bool) env('LEXICON_EXCHANGE', false),
        'api' => (bool) env('LEXICON_API', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Editorial (module "editorial")
    |--------------------------------------------------------------------------
    |
    | allow_self_review: let a reviewer approve their own submission. Off by
    | default so every change is seen by a second person.
    |
    */

    'editorial' => [
        'allow_self_review' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | REST API (module "api")
    |--------------------------------------------------------------------------
    |
    | middleware: applied to every route. Write routes additionally use
    | write_middleware (put your authentication guard there).
    | rate_limit: requests per minute per user or IP.
    |
    */

    'api' => [
        'prefix' => 'api/lexicon/v1',
        'middleware' => ['api'],
        'write_middleware' => ['auth'],
        'rate_limit' => 60,
        'per_page' => 20,
    ],

];
