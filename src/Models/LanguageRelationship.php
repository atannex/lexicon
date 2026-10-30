<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Enums\Certainty;
use Atannex\Lexicon\Enums\LanguageRelationshipType;
use Atannex\Lexicon\Models\Concerns\BelongsToTenant;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An asserted relationship between two language identities, with its
 * certainty and the evidence for it.
 *
 * @property int $id
 * @property string $tenant
 * @property int $from_language_id
 * @property int $to_language_id
 * @property LanguageRelationshipType $type
 * @property Certainty $certainty
 * @property int|null $source_id
 * @property string|null $notes
 */
class LanguageRelationship extends Model
{
    use BelongsToTenant;
    use UsesLexiconTable;

    protected $fillable = ['type', 'certainty', 'source_id', 'notes'];

    protected function baseTable(): string
    {
        return 'language_relationships';
    }

    protected static function sharesAcrossTenants(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return ['type' => LanguageRelationshipType::class, 'certainty' => Certainty::class];
    }

    /** @return BelongsTo<Language, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'from_language_id');
    }

    /** @return BelongsTo<Language, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'to_language_id');
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
