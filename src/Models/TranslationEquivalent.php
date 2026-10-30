<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Enums\EquivalenceType;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A translation of one sense: free text in another language, a link to a
 * sense in another lexicon language, or both. A sense may have any number
 * of equivalents, each with its own closeness (EquivalenceType).
 *
 * @property int $id
 * @property int $sense_id
 * @property string $written_in
 * @property string|null $text
 * @property int|null $target_sense_id
 * @property EquivalenceType $type
 * @property string|null $notes
 * @property int $position
 * @property-read Sense $sense
 * @property-read Sense|null $target
 */
class TranslationEquivalent extends Model
{
    use UsesLexiconTable;

    protected $fillable = ['written_in', 'text', 'type', 'notes', 'position'];

    protected function baseTable(): string
    {
        return 'translation_equivalents';
    }

    protected function casts(): array
    {
        return ['type' => EquivalenceType::class, 'position' => 'integer'];
    }

    /** @return BelongsTo<Sense, $this> */
    public function sense(): BelongsTo
    {
        return $this->belongsTo(Sense::class);
    }

    /** @return BelongsTo<Sense, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Sense::class, 'target_sense_id');
    }
}
