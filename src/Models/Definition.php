<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Contracts\OrthographyProfile;
use Atannex\Lexicon\Models\Concerns\HasSearchableText;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Atannex\Lexicon\Orthography\OrthographyRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A definition of a sense, written in a named language (a BCP 47 tag or a
 * lexicon language code). No language is privileged: a sense may be
 * defined in Nweh only, in French and English, or in all three.
 *
 * @property int $id
 * @property int $sense_id
 * @property int $lexeme_id
 * @property string $written_in
 * @property string $text
 * @property int $position
 * @property-read Sense $sense
 */
class Definition extends Model
{
    use HasSearchableText;
    use UsesLexiconTable;

    protected $fillable = ['written_in', 'text', 'position'];

    protected $hidden = ['search_exact', 'search_toneless', 'search_bare'];

    protected function baseTable(): string
    {
        return 'definitions';
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    protected function searchProfile(): OrthographyProfile
    {
        return app(OrthographyRegistry::class)->default();
    }

    /** @return BelongsTo<Sense, $this> */
    public function sense(): BelongsTo
    {
        return $this->belongsTo(Sense::class);
    }
}
