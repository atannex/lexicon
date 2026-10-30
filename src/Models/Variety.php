<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Enums\VarietyKind;
use Atannex\Lexicon\Models\Concerns\BelongsToTenant;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant
 * @property int $language_id
 * @property string $code
 * @property string $name
 * @property string|null $native_name
 * @property list<string>|null $alternative_names
 * @property VarietyKind $kind
 * @property string|null $description
 * @property array<string, mixed>|null $geography
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property-read Language $language
 */
class Variety extends Model
{
    use BelongsToTenant;
    use UsesLexiconTable;

    protected $fillable = ['code', 'name', 'native_name', 'alternative_names', 'kind', 'description', 'geography', 'is_active', 'metadata'];

    protected function baseTable(): string
    {
        return 'language_varieties';
    }

    protected static function sharesAcrossTenants(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'alternative_names' => 'array',
            'geography' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'kind' => VarietyKind::class,
        ];
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
