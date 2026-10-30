<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another name a language is known by: an endonym, exonym, historical or
 * alternative spelling, recorded with the language it is written in.
 *
 * @property int $id
 * @property int $language_id
 * @property string $name
 * @property string $kind alternative | endonym | exonym | historical
 * @property string|null $written_in
 * @property int|null $source_id
 * @property string|null $notes
 */
class LanguageName extends Model
{
    use UsesLexiconTable;

    public const array KINDS = ['alternative', 'endonym', 'exonym', 'historical'];

    protected $fillable = ['name', 'kind', 'written_in', 'source_id', 'notes'];

    protected function baseTable(): string
    {
        return 'language_names';
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
