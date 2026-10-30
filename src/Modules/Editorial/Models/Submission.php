<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Editorial\Models;

use Atannex\Lexicon\Models\Concerns\BelongsToTenant;
use Atannex\Lexicon\Models\Concerns\HasUid;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Source;
use Atannex\Lexicon\Modules\Editorial\Enums\SubmissionKind;
use Atannex\Lexicon\Modules\Editorial\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uid
 * @property string $tenant
 * @property int $language_id
 * @property int|null $lexeme_id
 * @property SubmissionKind $kind
 * @property array<string, mixed> $payload
 * @property SubmissionStatus $status
 * @property string|null $note
 * @property int|null $source_id
 * @property int|null $contributor_id
 * @property string $submitted_by
 * @property string|null $decided_by
 * @property Carbon|null $decided_at
 * @property int|null $result_id id of the record the approval created
 * @property-read Language $language
 * @property-read Lexeme|null $lexeme
 * @property-read Collection<int, Review> $reviews
 */
class Submission extends Model
{
    use BelongsToTenant;
    use HasUid;
    use UsesLexiconTable;

    protected $fillable = ['kind', 'payload', 'note', 'source_id', 'contributor_id'];

    protected function baseTable(): string
    {
        return 'submissions';
    }

    protected function casts(): array
    {
        return ['kind' => SubmissionKind::class, 'status' => SubmissionStatus::class, 'payload' => 'array', 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** @return BelongsTo<Lexeme, $this> */
    public function lexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class);
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->orderBy('id');
    }
}
