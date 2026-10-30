<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models\Concerns;

use Atannex\Lexicon\Contracts\OrthographyProfile;
use Atannex\Lexicon\Enums\MatchSensitivity;

/**
 * Running text (definitions, examples) keeps its original in `text` and
 * three derived search columns, one per MatchSensitivity.
 *
 * @property string $text
 * @property string $search_exact
 * @property string $search_toneless
 * @property string $search_bare
 */
trait HasSearchableText
{
    abstract protected function searchProfile(): OrthographyProfile;

    public static function bootHasSearchableText(): void
    {
        static::saving(static function (self $model): void {
            if ($model->isDirty('text') || ! $model->exists) {
                $model->refreshSearchText();
            }
        });
    }

    /** Recompute the search columns from the original text. */
    public function refreshSearchText(): void
    {
        $profile = $this->searchProfile();

        foreach (MatchSensitivity::cases() as $sensitivity) {
            $this->setAttribute($sensitivity->textColumn(), $profile->searchKey($this->text, $sensitivity));
        }
    }
}
