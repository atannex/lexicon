<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Enums\RelationType;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A directed lexical relation: from → type → to. Sense ids narrow it to
 * particular meanings; without them it holds between whole lexemes.
 *
 * @property int $id
 * @property int $from_lexeme_id
 * @property int|null $from_sense_id
 * @property int $to_lexeme_id
 * @property int|null $to_sense_id
 * @property RelationType $type
 * @property string|null $notes
 * @property-read Lexeme $from
 * @property-read Lexeme $to
 */
class Relation extends Model
{
    use UsesLexiconTable;

    protected $fillable = ['type', 'notes'];

    protected function baseTable(): string
    {
        return 'relations';
    }

    protected function casts(): array
    {
        return ['type' => RelationType::class];
    }

    /** @return BelongsTo<Lexeme, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class, 'from_lexeme_id');
    }

    /** @return BelongsTo<Lexeme, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class, 'to_lexeme_id');
    }

    /** @return BelongsTo<Sense, $this> */
    public function fromSense(): BelongsTo
    {
        return $this->belongsTo(Sense::class, 'from_sense_id');
    }

    /** @return BelongsTo<Sense, $this> */
    public function toSense(): BelongsTo
    {
        return $this->belongsTo(Sense::class, 'to_sense_id');
    }
}
