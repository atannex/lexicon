<?php

declare(strict_types=1);

use Atannex\Lexicon\Actions\AddTranslationEquivalent;
use Atannex\Lexicon\Actions\RelateLexemes;
use Atannex\Lexicon\Data\EquivalentData;
use Atannex\Lexicon\Enums\Certainty;
use Atannex\Lexicon\Enums\EquivalenceType;
use Atannex\Lexicon\Enums\LanguageRelationshipType;
use Atannex\Lexicon\Enums\RelationType;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Facades\Lexicon;

it('stores a directed relation once and reads it from both ends', function (): void {
    $language = language();
    $broad = entry($language, 'broad');
    $narrow = entry($language, 'narrow');

    $relation = app(RelateLexemes::class)->handle($broad, RelationType::Narrower, $narrow);

    expect($relation->type)->toBe(RelationType::Narrower)
        ->and($narrow->incomingRelations()->first()?->type->inverse())->toBe(RelationType::Broader);

    // The same fact stated from the other end is a duplicate.
    expect(fn () => app(RelateLexemes::class)->handle($narrow, RelationType::Broader, $broad))->toThrow(IntegrityViolation::class);
});

it('relates individual senses', function (): void {
    $language = language();
    $a = entry($language, 'a');
    $b = entry($language, 'b');

    $relation = app(RelateLexemes::class)->handle($a, RelationType::Synonym, $b, $a->senses()->first(), $b->senses()->first());

    expect($relation->from_sense_id)->toBe($a->senses()->value('id'));

    expect(fn () => app(RelateLexemes::class)->handle($a, RelationType::Antonym, $b, $b->senses()->first()))->toThrow(IntegrityViolation::class);
});

it('keeps cross-language links distinct from in-language relations', function (): void {
    $one = entry(language('one'), 'Ngangà');
    $two = entry(language('two'), 'Mbeqlěq');

    expect(app(RelateLexemes::class)->handle($one, RelationType::CrossLanguageEquivalent, $two)->exists)->toBeTrue()
        ->and(fn () => app(RelateLexemes::class)->handle($one, RelationType::Synonym, $two))->toThrow(IntegrityViolation::class);
});

it('allows many translation equivalents of different closeness per sense', function (): void {
    $target = entry(language('two'), 'Mbeqlěq');
    $entry = entry(language('one'), 'Ngangà');
    $sense = $entry->senses()->firstOrFail();

    app(AddTranslationEquivalent::class)->handle($sense, new EquivalentData('en', 'first', type: EquivalenceType::Near));
    app(AddTranslationEquivalent::class)->handle($sense, new EquivalentData('en', 'second', type: EquivalenceType::Broader));
    app(AddTranslationEquivalent::class)->handle($sense, new EquivalentData('two', targetSenseId: $target->senses()->value('id')));

    expect($sense->equivalents()->count())->toBe(3)
        ->and($sense->equivalents()->whereNotNull('target_sense_id')->first()?->target?->lexeme->lemmaText())->toBe('Mbeqlěq');
});

it('documents relationships between identities without merging them', function (): void {
    $mmouck = language('mmouck-test', name: "M'mouck");
    $mmock = language('mmock-test', name: "M'Mock");
    $evidence = source();

    $relationship = Lexicon::languages()->relate($mmouck, LanguageRelationshipType::MutuallyIntelligible, $mmock, Certainty::Disputed, $evidence->uid, 'test assertion');

    expect($relationship->certainty)->toBe(Certainty::Disputed)
        ->and(Lexicon::languages()->get('mmouck-test')->name)->toBe("M'mouck")
        ->and(Lexicon::languages()->get('mmock-test')->name)->toBe("M'Mock")
        ->and(Lexicon::languages()->all())->toHaveCount(2);
});
