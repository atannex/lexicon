<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

enum Certainty: string
{
    case Attested = 'attested';
    case Proposed = 'proposed';
    case Disputed = 'disputed';
}
