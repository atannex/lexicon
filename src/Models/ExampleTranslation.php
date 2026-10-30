<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $example_id
 * @property string $written_in
 * @property string $text
 */
class ExampleTranslation extends Model
{
    use UsesLexiconTable;

    protected $fillable = ['written_in', 'text'];

    protected function baseTable(): string
    {
        return 'example_translations';
    }

    /** @return BelongsTo<Example, $this> */
    public function example(): BelongsTo
    {
        return $this->belongsTo(Example::class);
    }
}
