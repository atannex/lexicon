<?php

declare(strict_types=1);

use Atannex\Lexicon\Actions\Attest;
use Atannex\Lexicon\Data\ContributorData;
use Atannex\Lexicon\Enums\ConsentStatus;
use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Exceptions\InvalidTransition;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Attestation;
use Illuminate\Database\QueryException;

it('attests forms, senses and definitions to sources', function (): void {
    $entry = entry(language(), 'Ngangà', ['sourceId' => source()->id]);
    $other = source(title: 'Archive tape 7');
    $definition = $entry->senses()->firstOrFail()->definitions()->firstOrFail();

    app(Attest::class)->handle($definition, $other, locator: '12:40', quotation: 'as said on the tape');

    expect($entry->attestations()->count())->toBe(2)
        ->and(Attestation::query()->where('attestable_type', 'lexicon.definition')->first()?->attestable?->is($definition))->toBeTrue();
});

it('will not publish an entry whose source lacks consent', function (ConsentStatus $consent): void {
    $entry = entry(language(), 'Ngangà', ['sourceId' => source($consent)->id]);

    Lexicon::language($entry->language)->entries()->publish($entry);
})->with([ConsentStatus::Unknown, ConsentStatus::Pending, ConsentStatus::Withdrawn])->throws(InvalidTransition::class);

it('requires a sense before publication', function (): void {
    $entry = entry(language(), 'Ngangà', ['senses' => []]);

    Lexicon::language($entry->language)->entries()->publish($entry);
})->throws(IntegrityViolation::class);

it('follows the status transitions', function (): void {
    $entry = entry(language(), 'Ngangà');
    $entries = Lexicon::language($entry->language)->entries();

    $entries->changeStatus($entry, EntryStatus::Submitted);
    expect(fn () => $entries->publish($entry))->toThrow(InvalidTransition::class);

    $entries->changeStatus($entry, EntryStatus::UnderReview);
    $entries->changeStatus($entry, EntryStatus::Approved);
    $entries->publish($entry);

    expect($entry->status)->toBe(EntryStatus::Published)->and($entry->published_at)->not->toBeNull();
});

it('does not let a cited source be deleted', function (): void {
    $source = source();
    entry(language(), 'Ngangà', ['sourceId' => $source->id]);

    $source->delete();
})->throws(QueryException::class);

it('deletes an entry with everything that belongs to it', function (): void {
    $source = source();
    $entry = entry(language(), 'Ngangà', ['sourceId' => $source->id]);

    Lexicon::language($entry->language)->entries()->delete($entry);

    expect(Attestation::query()->count())->toBe(0)
        ->and($source->fresh())->not->toBeNull()
        ->and(DB::table('lex_forms')->count() + DB::table('lex_senses')->count() + DB::table('lex_definitions')->count())->toBe(0);
});

it('credits contributors only when they agreed to public credit', function (): void {
    $anonymous = Lexicon::createContributor(new ContributorData('Speaker A'));
    $credited = Lexicon::createContributor(new ContributorData('Speaker B', userId: 7, creditAs: 'B.', creditPublicly: true));

    expect($anonymous->publicCredit())->toBeNull()
        ->and($credited->publicCredit())->toBe('B.')
        ->and(fn () => Lexicon::createContributor(new ContributorData('Again', userId: 7)))->toThrow(IntegrityViolation::class);
});
