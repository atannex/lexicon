<?php

declare(strict_types=1);

use Atannex\Lexicon\Enums;
use Atannex\Lexicon\Modules\Editorial\Enums as Editorial;

/*
 * SQLite ignores string lengths; MySQL and PostgreSQL reject values that
 * are too long. Every enum stored in a column must fit it. (Regression:
 * "changes_requested" overflowed a 16-character status column on MySQL.)
 */
it('fits every enum value into its column', function (string $enum, int $width): void {
    foreach ($enum::cases() as $case) {
        expect(strlen($case->value))->toBeLessThanOrEqual($width, "{$enum}::{$case->name}");
    }
})->with([
    [Enums\EntryStatus::class, 16],
    [Enums\Visibility::class, 16],
    [Enums\FormKind::class, 16],
    [Enums\OrthographyKind::class, 16],
    [Enums\VarietyKind::class, 16],
    [Enums\DocumentationStatus::class, 16],
    [Enums\LanguageRelationshipType::class, 32],
    [Enums\Certainty::class, 16],
    [Enums\RelationType::class, 32],
    [Enums\EquivalenceType::class, 16],
    [Enums\SourceKind::class, 32],
    [Enums\ConsentStatus::class, 16],
    [Editorial\SubmissionKind::class, 32],
    [Editorial\SubmissionStatus::class, 24],
    [Editorial\ReviewDecision::class, 16],
]);
