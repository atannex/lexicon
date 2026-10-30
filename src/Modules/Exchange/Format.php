<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Exchange;

/**
 * jsonl: one entry per line, streamed both ways; the lossless choice for
 *        large dictionaries.
 * json:  one document with the language and every entry; import reads it
 *        into memory, so prefer jsonl beyond a few thousand entries.
 * csv:   one row per sense with its first definition; lossy (see docs).
 */
enum Format: string
{
    case JsonLines = 'jsonl';
    case Json = 'json';
    case Csv = 'csv';
}
