<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Editorial\Models;

use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Atannex\Lexicon\Modules\Editorial\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in a submission's review: the audit trail of who did what.
 *
 * @property int $id
 * @property int $submission_id
 * @property string $reviewer
 * @property ReviewDecision $decision
 * @property string|null $comment
 */
class Review extends Model
{
    use UsesLexiconTable;

    protected $fillable = ['reviewer', 'decision', 'comment'];

    protected function baseTable(): string
    {
        return 'reviews';
    }

    protected function casts(): array
    {
        return ['decision' => ReviewDecision::class];
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
