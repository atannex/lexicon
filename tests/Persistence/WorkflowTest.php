<?php

declare(strict_types=1);

use Atannex\Lexicon\Actions\SetLemma;
use Atannex\Lexicon\Authorization\ReadAccess;
use Atannex\Lexicon\Data\CreateLexemeData;
use Atannex\Lexicon\Data\DefinitionData;
use Atannex\Lexicon\Data\FormData;
use Atannex\Lexicon\Data\RegisterLanguageData;
use Atannex\Lexicon\Data\RegisterOrthographyData;
use Atannex\Lexicon\Data\RegisterVarietyData;
use Atannex\Lexicon\Data\SenseData;
use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Enums\FormKind;
use Atannex\Lexicon\Enums\SearchField;
use Atannex\Lexicon\Enums\Visibility;
use Atannex\Lexicon\Events\LexemeCreated;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Attestation;
use Atannex\Lexicon\Models\Lexeme;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/*
 * The core acceptance path: register language → create entry → attach
 * forms → define senses → add definitions → record source → validate →
 * persist → search → retrieve.
 */
it('runs the complete dictionary workflow', function (): void {
    Event::fake([LexemeCreated::class]);

    // Register a language and document a variety and an orthography.
    $language = Lexicon::languages()->register(new RegisterLanguageData(code: 'testlang', name: 'Test language', nativeName: 'Ngangà'));
    Lexicon::language('testlang')->addVariety(new RegisterVarietyData(code: 'north', name: 'Northern'));
    Lexicon::language('testlang')->addOrthography(new RegisterOrthographyData(code: 'community', name: 'Community spelling', rules: [
        'tone_marks' => ["\u{0300}", "\u{0301}", "\u{0302}", "\u{030C}", "\u{0304}"],
    ]));

    // Record where the evidence comes from.
    $source = source();

    // Validate raw input the way an HTTP or import client would.
    $data = CreateLexemeData::fromArray([
        'lemma' => 'Mbeqlěq',
        'part_of_speech' => 'noun',
        'source_id' => $source->uid,
        'locator' => 'notebook 2, p. 14',
        'visibility' => 'public',
        'forms' => [
            ['text' => 'Mbeqleq', 'kind' => 'variant', 'variety' => 'north'],
            ['text' => 'mbɛʔlɛ̌ʔ', 'kind' => 'phonetic'],
        ],
        'senses' => [
            ['definitions' => [['text' => 'first test gloss', 'written_in' => 'en'], ['text' => 'première glose', 'written_in' => 'fr']]],
            ['definitions' => [['text' => 'second test gloss', 'written_in' => 'en']], 'examples' => [['text' => 'Mbeqlěq àbɔ́', 'translations' => ['en' => 'test example']]]],
        ],
    ]);

    $entry = Lexicon::language('testlang')->entries()->create($data);
    Lexicon::language('testlang')->entries()->publish($entry);

    Event::assertDispatched(LexemeCreated::class);

    // Persisted exactly.
    $stored = Lexeme::query()->with(['forms', 'senses.definitions', 'senses.examples.translations'])->findOrFail($entry->id);

    expect($stored->lemmaText())->toBe('Mbeqlěq')
        ->and($stored->status)->toBe(EntryStatus::Published)
        ->and($stored->forms)->toHaveCount(3)
        ->and($stored->formsOfKind(FormKind::Phonetic)->first()?->text)->toBe('mbɛʔlɛ̌ʔ')
        ->and($stored->formsOfKind(FormKind::Variant)->first()?->variety?->code)->toBe('north')
        ->and($stored->senses)->toHaveCount(2)
        ->and($stored->senses[0]->definitionIn('fr')?->text)->toBe('première glose')
        ->and($stored->senses[1]->examples[0]->translations[0]->text)->toBe('test example')
        ->and(Attestation::query()->where('source_id', $source->id)->value('locator'))->toBe('notebook 2, p. 14');

    // Searchable, tone-insensitively, through the variant and the definition.
    expect(lemmas(Lexicon::language('testlang')->search('mbeqleq')->exact()->whole()->get()))->toBe(['Mbeqlěq'])
        ->and(lemmas(Lexicon::language('testlang')->search('MBEQLEQ')->toneInsensitive()->get()))->toBe(['Mbeqlěq'])
        ->and(lemmas(Lexicon::language('testlang')->search('première')->in(SearchField::Definitions)->get()))->toBe(['Mbeqlěq'])
        ->and(Lexicon::language('testlang')->search('àbɔ́')->in(SearchField::Examples)->get()->first()?->field)->toBe(SearchField::Examples);

    // Retrievable by its public id and listed in the language.
    expect(Lexicon::language($language)->entries()->find($entry->uid)?->is($entry))->toBeTrue()
        ->and(Lexicon::language($language)->entries()->paginate()->total())->toBe(1);
});

it('rejects invalid input before anything is stored', function (array $input): void {
    expect(fn () => CreateLexemeData::fromArray($input))->toThrow(ValidationException::class);
})->with([
    'no lemma' => [['part_of_speech' => 'noun']],
    'control characters' => [['lemma' => "bad\x07"]],
    'lemma too long' => [['lemma' => str_repeat('a', 201)]],
    'published on create' => [['lemma' => 'x', 'status' => 'published']],
    'unknown visibility' => [['lemma' => 'x', 'visibility' => 'secret']],
]);

it('stores nothing when part of an entry fails', function (): void {
    $language = language();

    expect(fn () => Lexicon::language($language)->entries()->create(new CreateLexemeData(
        lemma: 'Ngangà',
        forms: [new FormData('variant', variety: 'no-such-variety')],
    )))->toThrow(IntegrityViolation::class);

    expect(Lexeme::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('supports several senses with definitions in several languages and none in English', function (): void {
    $language = language();

    $entry = entry($language, 'Betaâgnúá', ['senses' => [
        new SenseData(definitions: [new DefinitionData('glose', 'fr'), new DefinitionData('Ngangà', 'testlang')]),
        new SenseData(definitions: [new DefinitionData('andere', 'de')]),
    ]]);

    $languages = $entry->senses()->with('definitions')->get()->flatMap->definitions->pluck('written_in')->all();

    expect($languages)->toBe(['fr', 'testlang', 'de']);
});

it('orders entries by the orthography alphabet', function (): void {
    $language = language();
    Lexicon::language($language)->addOrthography(new RegisterOrthographyData(code: 'std', name: 'Standard', rules: ['alphabet' => ['a', 'b', 'gh', 'g', 'n', 'ŋ']]));

    foreach (['ŋa', 'ga', 'gha', 'na', 'ba'] as $word) {
        published($language, $word);
    }

    $order = Lexicon::language($language)->entries()->get()->map(fn (Lexeme $l) => $l->lemmaText())->all();

    expect($order)->toBe(['ba', 'gha', 'ga', 'na', 'ŋa']);
});

it('promotes a variant to lemma and demotes the old lemma', function (): void {
    $language = language();
    $entry = entry($language, 'old', ['forms' => [new FormData('new')]]);
    $variant = $entry->forms()->where('text', 'new')->firstOrFail();

    app(SetLemma::class)->handle($entry, $variant);

    expect($entry->fresh()?->lemmaText())->toBe('new')
        ->and($entry->forms()->where('text', 'old')->value('kind'))->toBe(FormKind::Variant);
});

it('keeps unpublished and non-public entries out of default reads', function (): void {
    $language = language();
    entry($language, 'draft');
    published($language, 'restricted', ['visibility' => Visibility::Restricted]);
    published($language, 'open');

    expect(Lexicon::language($language)->entries()->get()->map->lemmaText()->all())->toBe(['open'])
        ->and(Lexicon::language($language)->search('r')->get()->isEmpty())->toBeTrue()
        ->and(Lexicon::language($language)->entries()->withAccess(ReadAccess::everything())->count())->toBe(3);
});
