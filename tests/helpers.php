<?php

declare(strict_types=1);

use Atannex\Lexicon\Data\CreateLexemeData;
use Atannex\Lexicon\Data\CreateSourceData;
use Atannex\Lexicon\Data\DefinitionData;
use Atannex\Lexicon\Data\RegisterLanguageData;
use Atannex\Lexicon\Data\SenseData;
use Atannex\Lexicon\Enums\ConsentStatus;
use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Enums\SourceKind;
use Atannex\Lexicon\Enums\Visibility;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Source;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;

/*
 * Test fixtures. Spellings such as "Ngangà" exercise Unicode handling only;
 * the glosses attached to them in tests ("test gloss") are placeholders,
 * not claims about what any word means.
 */

function language(string $code = 'testlang', bool $shared = false, string $name = 'Test language'): Language
{
    return Lexicon::languages()->register(new RegisterLanguageData(code: $code, name: $name, shared: $shared));
}

function source(ConsentStatus $consent = ConsentStatus::Obtained, string $title = 'Field session 1'): Source
{
    return Lexicon::createSource(new CreateSourceData(kind: SourceKind::FieldNotes, title: $title, consent: $consent));
}

/** @param array<string, mixed> $overrides named arguments for CreateLexemeData */
function entry(Language $language, string $lemma, array $overrides = []): Lexeme
{
    $data = new CreateLexemeData(...array_replace([
        'lemma' => $lemma,
        'senses' => [new SenseData(definitions: [new DefinitionData('test gloss for '.$lemma, 'en')])],
        'visibility' => Visibility::Public,
    ], $overrides));

    return Lexicon::language($language)->entries()->create($data);
}

function published(Language $language, string $lemma, array $overrides = []): Lexeme
{
    $lexeme = entry($language, $lemma, $overrides);

    return Lexicon::language($language)->entries()->changeStatus($lexeme, EntryStatus::Published);
}

/** @return list<string> lemma texts of a search result, best first */
function lemmas(iterable $results): array
{
    $out = [];

    foreach ($results as $hit) {
        $out[] = $hit->lexeme->lemma->text;
    }

    return $out;
}

/** An authenticated user for authorization tests; abilities are granted by the test. */
function user(int $id = 1): GenericUser
{
    return new GenericUser(['id' => $id, 'name' => "User {$id}"]);
}

/** Grant abilities to the listed user ids. */
function grant(array $abilities, array $userIds): void
{
    foreach ($abilities as $ability) {
        Gate::define($ability, fn ($user) => in_array($user->getAuthIdentifier(), $userIds, true));
    }
}
