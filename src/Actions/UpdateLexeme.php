<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\Keep;
use Atannex\Lexicon\Data\UpdateLexemeData;
use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Support\References;
use Illuminate\Contracts\Events\Dispatcher;

/** Changes an entry's own fields. Its language never changes once created. */
final class UpdateLexeme extends Action
{
    public function __construct(private readonly References $references, private readonly Dispatcher $events) {}

    public function handle(Lexeme $lexeme, UpdateLexemeData $data, ?string $actor = null): Lexeme
    {
        $this->references->assertOwned($lexeme);

        if (! $data->partOfSpeech instanceof Keep) {
            $lexeme->part_of_speech = $data->partOfSpeech;
        }

        if (! $data->homograph instanceof Keep) {
            $lexeme->homograph = $data->homograph;
        }

        if (! $data->variety instanceof Keep) {
            $lexeme->variety_id = $this->references->variety($lexeme->language, $data->variety)?->id;
        }

        if (! $data->visibility instanceof Keep) {
            $lexeme->visibility = $data->visibility;
        }

        if (! $data->metadata instanceof Keep) {
            $lexeme->metadata = $data->metadata ?: null;
        }

        if ($lexeme->isDirty()) {
            $lexeme->save();
            $this->events->dispatch(new LexemeUpdated($lexeme, 'fields', $actor));
        }

        return $lexeme;
    }
}
