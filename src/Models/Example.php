<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Contracts\OrthographyProfile;
use Atannex\Lexicon\Models\Concerns\HasSearchableText;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Atannex\Lexicon\Orthography\OrthographyRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A usage example in the entry's language, with translations as separate
 * records so each keeps its own language tag.
 *
 * @property int $id
 * @property int $sense_id
 * @property int $lexeme_id
 * @property int|null $variety_id
 * @property int|null $orthography_id
 * @property string $text
 * @property string|null $context
 * @property string|null $audio
 * @property int $position
 * @property-read Sense $sense
 * @property-read Collection<int, ExampleTranslation> $translations
 */
class Example extends Model
{
    use HasSearchableText;
    use UsesLexiconTable;

    protected $fillable = ['variety_id', 'orthography_id', 'text', 'context', 'audio', 'position'];

    protected $hidden = ['search_exact', 'search_toneless', 'search_bare'];

    protected function baseTable(): string
    {
        return 'examples';
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    protected function searchProfile(): OrthographyProfile
    {
        $languageId = Lexeme::query()->whereKey($this->lexeme_id)->value('language_id');

        return app(OrthographyRegistry::class)->forOrthographyId($this->orthography_id, is_numeric($languageId) ? (int) $languageId : null);
    }

    /** @return BelongsTo<Sense, $this> */
    public function sense(): BelongsTo
    {
        return $this->belongsTo(Sense::class);
    }

    /** @return HasMany<ExampleTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ExampleTranslation::class);
    }
}
