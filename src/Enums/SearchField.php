<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

enum SearchField: string
{
    case Forms = 'forms';
    case Definitions = 'definitions';
    case Examples = 'examples';
}
