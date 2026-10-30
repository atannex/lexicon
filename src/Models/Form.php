<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Enums\FormKind;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Atannex\Lexicon\Orthography\OrthographyRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One written (or transcribed) form of a lexeme. `text` is the original
 * spelling, stored exactly as given; the key_* columns are derived from it
 * by the orthography's profile whenever it is saved.
 *
 * @property int $id
 * @property int $lexeme_id
 * @property string $tenant
 * @property int $language_id
 * @property int|null $variety_id
 * @property int|null $orthography_id
 * @property FormKind $kind
 * @property string $text
 * @property string $key_exact
 * @property string $key_toneless
 * @property string $key_bare
 * @property string $sort_key
 * @property array<string, mixed>|null $features
 * @property int $position
 * @property-read Lexeme $lexeme
 * @property-read Orthography|null $orthography
 * @property-read Variety|null $variety
 */
class Form extends Model
{
    use UsesLexiconTable;

    public const int KEY_LENGTH = 255;

    public const int SORT_KEY_LENGTH = 512;

    protected $fillable = ['variety_id', 'orthography_id', 'kind', 'text', 'features', 'position'];

    protected function baseTable(): string
    {
        return 'forms';
    }

    protected function casts(): array
    {
        return ['kind' => FormKind::class, 'features' => 'array', 'position' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $form): void {
            if ($form->isDirty(['text', 'orthography_id']) || ! $form->exists) {
                $form->refreshKeys();
            }
        });
    }

    /** Recompute every derived key from the original spelling. */
    public function refreshKeys(): void
    {
        $keys = app(OrthographyRegistry::class)->forOrthographyId($this->orthography_id, $this->language_id)->keys($this->text);

        $this->key_exact = mb_substr($keys->exact, 0, self::KEY_LENGTH);
        $this->key_toneless = mb_substr($keys->toneless, 0, self::KEY_LENGTH);
        $this->key_bare = mb_substr($keys->bare, 0, self::KEY_LENGTH);
        $this->sort_key = substr($keys->sort, 0, self::SORT_KEY_LENGTH);
    }

    /** @return BelongsTo<Lexeme, $this> */
    public function lexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class);
    }

    /** @return BelongsTo<Orthography, $this> */
    public function orthography(): BelongsTo
    {
        return $this->belongsTo(Orthography::class);
    }

    /** @return BelongsTo<Variety, $this> */
    public function variety(): BelongsTo
    {
        return $this->belongsTo(Variety::class);
    }
}
