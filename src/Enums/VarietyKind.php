<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

enum VarietyKind: string
{
    case Dialect = 'dialect';
    case Regional = 'regional';
    case Historical = 'historical';
    case Community = 'community';
    case Sociolect = 'sociolect';
    case Other = 'other';
}
