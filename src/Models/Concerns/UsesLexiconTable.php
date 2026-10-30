<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models\Concerns;

use Atannex\Lexicon\Support\Tables;

/** Applies the configured table prefix and connection. */
trait UsesLexiconTable
{
    abstract protected function baseTable(): string;

    public function getTable(): string
    {
        return Tables::name($this->baseTable());
    }

    public function getConnectionName(): ?string
    {
        return Tables::connection() ?? parent::getConnectionName();
    }
}
