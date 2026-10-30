<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

/**
 * Editorial status of a dictionary record. Only Published records are
 * visible to readers without editorial rights.
 */
enum EntryStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Published = 'published';
    case Archived = 'archived';

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Published, self::Archived],
            self::Submitted => [self::UnderReview, self::Draft, self::Rejected],
            self::UnderReview => [self::Approved, self::Rejected, self::Draft],
            self::Approved => [self::Published, self::Draft],
            self::Rejected => [self::Draft, self::Archived],
            self::Published => [self::Draft, self::Archived],
            self::Archived => [self::Draft],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }
}
