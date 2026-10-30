<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Support;

/** Table names with the configured prefix, shared by migrations and models. */
final class Tables
{
    public static function name(string $table): string
    {
        $prefix = config('lexicon.database.table_prefix', 'lex_');

        return (is_string($prefix) ? $prefix : 'lex_').$table;
    }

    public static function connection(): ?string
    {
        $connection = config('lexicon.database.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }
}
