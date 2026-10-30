<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Enums\ConsentStatus;
use Atannex\Lexicon\Enums\SourceKind;
use Atannex\Lexicon\Enums\Visibility;
use Atannex\Lexicon\Models\Concerns\BelongsToTenant;
use Atannex\Lexicon\Models\Concerns\HasUid;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Where evidence comes from: an interview, a speaker, a book, a recording.
 * `location` should be recorded no more precisely than the community is
 * comfortable publishing (a village or division, not a homestead).
 *
 * @property int $id
 * @property string $uid
 * @property string $tenant
 * @property SourceKind $kind
 * @property string $title
 * @property string|null $citation
 * @property list<string>|null $authors
 * @property string|null $publisher
 * @property string|null $published
 * @property string|null $url
 * @property Carbon|null $collected_on
 * @property string|null $location
 * @property string|null $license
 * @property ConsentStatus $consent
 * @property Visibility $visibility
 * @property int|null $contributor_id
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 */
class Source extends Model
{
    use BelongsToTenant;
    use HasUid;
    use UsesLexiconTable;

    protected $fillable = ['kind', 'title', 'citation', 'authors', 'publisher', 'published', 'url', 'collected_on', 'location', 'license', 'consent', 'visibility', 'contributor_id', 'notes', 'metadata'];

    protected function baseTable(): string
    {
        return 'sources';
    }

    protected function casts(): array
    {
        return [
            'kind' => SourceKind::class,
            'consent' => ConsentStatus::class,
            'visibility' => Visibility::class,
            'authors' => 'array',
            'metadata' => 'array',
            'collected_on' => 'date',
        ];
    }

    /** @return BelongsTo<Contributor, $this> */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }

    /** @return HasMany<Attestation, $this> */
    public function attestations(): HasMany
    {
        return $this->hasMany(Attestation::class);
    }
}
