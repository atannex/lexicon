<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models\Concerns;

use Illuminate\Support\Str;

/**
 * A public, stable identifier (ULID) for records exposed outside the
 * database, so numeric ids never leak through URLs or exports.
 *
 * @property string $uid
 */
trait HasUid
{
    public static function bootHasUid(): void
    {
        static::creating(static function (self $model): void {
            $model->uid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uid';
    }
}
