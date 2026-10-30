<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Support\Tables;
use Closure;
use Illuminate\Support\Facades\DB;

/** Shared plumbing: every write runs in one transaction on the lexicon connection. */
abstract class Action
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function transaction(Closure $callback): mixed
    {
        return DB::connection(Tables::connection())->transaction($callback);
    }
}
