<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Enums\DocumentationStatus;
use Atannex\Lexicon\Models\Concerns\BelongsToTenant;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A language identity. Varieties (dialects and other documented speech
 * forms) hang off it; how two identities relate is asserted separately in
 * LanguageRelationship, never implied by the name.
 *
 * @property int $id
 * @property string $tenant
 * @property string $code
 * @property string $name
 * @property string|null $native_name
 * @property string|null $iso639_3
 * @property array<string, string>|null $external_ids
 * @property string|null $description
 * @property array<string, mixed>|null $geography
 * @property DocumentationStatus $documentation_status
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 */
class Language extends Model
{
    use BelongsToTenant;
    use UsesLexiconTable;

    protected $fillable = ['code', 'name', 'native_name', 'iso639_3', 'external_ids', 'description', 'geography', 'documentation_status', 'is_active', 'metadata'];

    protected function baseTable(): string
    {
        return 'languages';
    }

    protected static function sharesAcrossTenants(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'external_ids' => 'array',
            'geography' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'documentation_status' => DocumentationStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** @return HasMany<LanguageName, $this> */
    public function names(): HasMany
    {
        return $this->hasMany(LanguageName::class);
    }

    /** @return HasMany<Variety, $this> */
    public function varieties(): HasMany
    {
        return $this->hasMany(Variety::class);
    }

    /** @return HasMany<Orthography, $this> */
    public function orthographies(): HasMany
    {
        return $this->hasMany(Orthography::class);
    }

    /** @return HasMany<Lexeme, $this> */
    public function lexemes(): HasMany
    {
        return $this->hasMany(Lexeme::class);
    }

    /** @return HasMany<LanguageRelationship, $this> */
    public function relationships(): HasMany
    {
        return $this->hasMany(LanguageRelationship::class, 'from_language_id');
    }

    public function defaultOrthography(): ?Orthography
    {
        return $this->orthographies->firstWhere('is_default', true) ?? $this->orthographies->first();
    }
}
