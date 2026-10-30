<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Enums\FormKind;
use Atannex\Lexicon\Enums\Visibility;
use Atannex\Lexicon\Models\Concerns\BelongsToTenant;
use Atannex\Lexicon\Models\Concerns\HasUid;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A lexical item: an abstract unit of the lexicon, not a string. Its
 * spellings are Forms (one of them the lemma), its meanings are Senses.
 *
 * @property int $id
 * @property string $uid
 * @property string $tenant
 * @property int $language_id
 * @property int|null $variety_id
 * @property int|null $lemma_form_id
 * @property string|null $part_of_speech
 * @property int|null $homograph
 * @property EntryStatus $status
 * @property Visibility $visibility
 * @property int|null $contributor_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Language $language
 * @property-read Variety|null $variety
 * @property-read Form|null $lemma
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Form> $forms
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Sense> $senses
 */
class Lexeme extends Model
{
    use BelongsToTenant;
    use HasUid;
    use UsesLexiconTable;

    protected $fillable = ['variety_id', 'part_of_speech', 'homograph', 'metadata'];

    protected function baseTable(): string
    {
        return 'lexemes';
    }

    protected function casts(): array
    {
        return [
            'status' => EntryStatus::class,
            'visibility' => Visibility::class,
            'metadata' => 'array',
            'homograph' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** @return BelongsTo<Variety, $this> */
    public function variety(): BelongsTo
    {
        return $this->belongsTo(Variety::class);
    }

    /** @return BelongsTo<Form, $this> */
    public function lemma(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'lemma_form_id');
    }

    /** @return HasMany<Form, $this> */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Sense, $this> */
    public function senses(): HasMany
    {
        return $this->hasMany(Sense::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Relation, $this> */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(Relation::class, 'from_lexeme_id');
    }

    /** @return HasMany<Relation, $this> */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(Relation::class, 'to_lexeme_id');
    }

    /** @return HasMany<Attestation, $this> */
    public function attestations(): HasMany
    {
        return $this->hasMany(Attestation::class);
    }

    /** @return BelongsTo<Contributor, $this> */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }

    public function lemmaText(): ?string
    {
        return $this->lemma?->text;
    }

    /** @return Collection<int, Form> */
    public function formsOfKind(FormKind $kind): Collection
    {
        return $this->forms->filter(static fn (Form $form): bool => $form->kind === $kind)->values();
    }

    /**
     * @param  Builder<self>  $query
     * @param  list<Visibility>  $visibilities
     */
    public function scopeReadable(Builder $query, array $visibilities, bool $publishedOnly = true): void
    {
        $query->whereIn($this->qualifyColumn('visibility'), array_map(static fn (Visibility $v): string => $v->value, $visibilities));

        if ($publishedOnly) {
            $query->where($this->qualifyColumn('status'), EntryStatus::Published->value);
        }
    }

    public function isPublished(): bool
    {
        return $this->status === EntryStatus::Published;
    }
}
