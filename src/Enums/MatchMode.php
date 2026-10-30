<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

enum MatchMode: string
{
    case Whole = 'whole';
    case Prefix = 'prefix';
    case Contains = 'contains';
}
