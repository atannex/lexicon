<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Models\Concerns\BelongsToTenant;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Evidence that a source supports a claim: that a form exists, that a
 * sense means what its definition says, that an example was said.
 *
 * @property int $id
 * @property string $tenant
 * @property int $source_id
 * @property string $attestable_type
 * @property int $attestable_id
 * @property int|null $lexeme_id
 * @property string|null $locator page, timestamp or archive reference
 * @property string|null $quotation the source's own wording
 * @property string|null $notes
 * @property int|null $contributor_id
 * @property Carbon|null $verified_at
 * @property string|null $verified_by
 * @property-read Source $source
 */
class Attestation extends Model
{
    use BelongsToTenant;
    use UsesLexiconTable;

    protected $fillable = ['locator', 'quotation', 'notes', 'contributor_id'];

    protected function baseTable(): string
    {
        return 'attestations';
    }

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return MorphTo<Model, $this> */
    public function attestable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Contributor, $this> */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }
}
