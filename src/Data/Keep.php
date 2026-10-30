<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

/** Marks an update field that was not supplied, as distinct from null. */
enum Keep
{
    case Value;
}
