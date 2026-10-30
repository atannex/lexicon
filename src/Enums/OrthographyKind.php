<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

enum OrthographyKind: string
{
    /** A writing system used by speakers (standard or community spelling). */
    case Orthography = 'orthography';
    /** A historical or superseded spelling convention. */
    case Historical = 'historical';
    /** A phonetic or phonemic notation such as IPA. */
    case Phonetic = 'phonetic';
    /** A romanisation or other transliteration scheme. */
    case Transliteration = 'transliteration';
}
