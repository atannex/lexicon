<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

/** How closely a translation equivalent matches a sense. */
enum EquivalenceType: string
{
    case Exact = 'exact';
    case Near = 'near';
    case Broader = 'broader';
    case Narrower = 'narrower';
    /** No direct equivalent; the text is an explanatory gloss. */
    case Explanatory = 'explanatory';
}
