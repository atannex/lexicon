<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Enums\OrthographyKind;
use Atannex\Lexicon\Models\Concerns\BelongsToTenant;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A writing system or notation. Rules (see OrthographyRules) or a named
 * profile control how its spellings compare and sort.
 *
 * @property int $id
 * @property string $tenant
 * @property int|null $language_id
 * @property string $code
 * @property string $name
 * @property OrthographyKind $kind
 * @property string $script ISO 15924
 * @property string $direction ltr | rtl
 * @property string|null $profile
 * @property array<string, mixed>|null $rules
 * @property bool $is_default
 * @property string|null $description
 */
class Orthography extends Model
{
    use BelongsToTenant;
    use UsesLexiconTable;

    protected $fillable = ['code', 'name', 'kind', 'script', 'direction', 'profile', 'rules', 'is_default', 'description'];

    protected function baseTable(): string
    {
        return 'orthographies';
    }

    protected static function sharesAcrossTenants(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return ['rules' => 'array', 'is_default' => 'boolean', 'kind' => OrthographyKind::class];
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
