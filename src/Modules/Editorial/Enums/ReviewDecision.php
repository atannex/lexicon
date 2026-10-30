<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Editorial\Enums;

enum ReviewDecision: string
{
    case Started = 'started';
    case Comment = 'comment';
    case RequestChanges = 'request_changes';
    case Resubmitted = 'resubmitted';
    case Approve = 'approve';
    case Reject = 'reject';
    case Withdraw = 'withdraw';
}
