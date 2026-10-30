<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Editorial\Models;

use Atannex\Lexicon\Models\Concerns\BelongsToTenant;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An immutable snapshot of an entry after a change.
 *
 * @property int $id
 * @property string $tenant
 * @property string $lexeme_uid
 * @property int $version
 * @property string $change
 * @property array<string, mixed>|null $snapshot null for a deletion
 * @property string|null $actor
 * @property Carbon|null $created_at
 */
class Revision extends Model
{
    use BelongsToTenant;
    use UsesLexiconTable;

    public const UPDATED_AT = null;

    protected $fillable = ['lexeme_uid', 'version', 'change', 'snapshot', 'actor'];

    protected function baseTable(): string
    {
        return 'revisions';
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): bool => false);
    }
}
