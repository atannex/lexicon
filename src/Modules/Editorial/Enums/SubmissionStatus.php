<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Editorial\Enums;

enum SubmissionStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case ChangesRequested = 'changes_requested';
    /** Approved and applied to the dictionary, in one transaction. */
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::ChangesRequested], true);
    }
}
