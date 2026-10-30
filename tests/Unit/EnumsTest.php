<?php

declare(strict_types=1);

use Atannex\Lexicon\Enums\ConsentStatus;
use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Enums\RelationType;

it('follows the editorial workflow', function (): void {
    expect(EntryStatus::Draft->canTransitionTo(EntryStatus::Submitted))->toBeTrue()
        ->and(EntryStatus::Submitted->canTransitionTo(EntryStatus::Published))->toBeFalse()
        ->and(EntryStatus::Approved->canTransitionTo(EntryStatus::Published))->toBeTrue()
        ->and(EntryStatus::Archived->canTransitionTo(EntryStatus::Published))->toBeFalse();
});

it('inverts directed relations and keeps symmetric ones', function (): void {
    expect(RelationType::Broader->inverse())->toBe(RelationType::Narrower)
        ->and(RelationType::DerivedFrom->inverse())->toBe(RelationType::DerivesTo)
        ->and(RelationType::Synonym->isSymmetric())->toBeTrue()
        ->and(RelationType::VariantOf->isSymmetric())->toBeFalse();

    foreach (RelationType::cases() as $type) {
        expect($type->inverse()->inverse())->toBe($type);
    }
});

it('only lets consented material be published', function (): void {
    expect(ConsentStatus::Obtained->permitsPublication())->toBeTrue()
        ->and(ConsentStatus::NotRequired->permitsPublication())->toBeTrue()
        ->and(ConsentStatus::Unknown->permitsPublication())->toBeFalse()
        ->and(ConsentStatus::Withdrawn->permitsPublication())->toBeFalse();
});
