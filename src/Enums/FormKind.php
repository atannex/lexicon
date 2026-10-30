<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

enum FormKind: string
{
    case Lemma = 'lemma';
    case Variant = 'variant';
    case Historical = 'historical';
    case Inflected = 'inflected';
    case Dialectal = 'dialectal';
    case Transliteration = 'transliteration';
    case Phonetic = 'phonetic';
}
