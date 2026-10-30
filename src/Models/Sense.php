<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One meaning of a lexeme. Definitions, examples and translation
 * equivalents attach here, not to the lexeme, because a word rarely maps
 * to a single meaning or a single word in another language.
 *
 * @property int $id
 * @property int $lexeme_id
 * @property int $position
 * @property string|null $semantic_domain
 * @property list<string>|null $labels usage and domain labels
 * @property string|null $usage_note
 * @property array<string, mixed>|null $metadata
 * @property-read Lexeme $lexeme
 * @property-read Collection<int, Definition> $definitions
 * @property-read Collection<int, Example> $examples
 * @property-read Collection<int, TranslationEquivalent> $equivalents
 */
class Sense extends Model
{
    use UsesLexiconTable;

    protected $fillable = ['position', 'semantic_domain', 'labels', 'usage_note', 'metadata'];

    protected function baseTable(): string
    {
        return 'senses';
    }

    protected function casts(): array
    {
        return ['labels' => 'array', 'metadata' => 'array', 'position' => 'integer'];
    }

    /** @return BelongsTo<Lexeme, $this> */
    public function lexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class);
    }

    /** @return HasMany<Definition, $this> */
    public function definitions(): HasMany
    {
        return $this->hasMany(Definition::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Example, $this> */
    public function examples(): HasMany
    {
        return $this->hasMany(Example::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<TranslationEquivalent, $this> */
    public function equivalents(): HasMany
    {
        return $this->hasMany(TranslationEquivalent::class)->orderBy('position')->orderBy('id');
    }

    /** Definitions written in one language, e.g. definitionsIn('fr'). */
    public function definitionIn(string $language): ?Definition
    {
        return $this->definitions->firstWhere('written_in', $language);
    }
}
