<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

/** How well a language is documented in this lexicon's view; not a vitality rating. */
enum DocumentationStatus: string
{
    case Undocumented = 'undocumented';
    case InProgress = 'in_progress';
    case Partial = 'partial';
    case Documented = 'documented';
}
