<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\ExampleData;
use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Example;
use Atannex\Lexicon\Models\Sense;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Support\Text;
use Illuminate\Contracts\Events\Dispatcher;

final class AddExample extends Action
{
    public function __construct(private readonly References $references, private readonly Attest $attest, private readonly Dispatcher $events) {}

    public function handle(Sense $sense, ExampleData $data, ?string $actor = null): Example
    {
        $example = $this->transaction(fn (): Example => $this->store($sense, $data));

        $this->events->dispatch(new LexemeUpdated($sense->lexeme, 'example', $actor));

        return $example;
    }

    /** @internal */
    public function store(Sense $sense, ExampleData $data): Example
    {
        $lexeme = $sense->lexeme;
        $this->references->assertOwned($lexeme);

        $example = new Example([
            'text' => Text::require($data->text, 'example', 5000),
            'context' => Text::optional($data->context, 'context'),
            'audio' => $data->audio,
            'position' => $sense->examples()->count(),
        ]);
        $example->sense()->associate($sense);
        $example->lexeme_id = $lexeme->id;
        $example->variety_id = $this->references->variety($lexeme->language, $data->variety)?->id;
        $example->orthography_id = $this->references->orthography($lexeme->language, $data->orthography)?->id;
        $example->save();

        foreach ($data->translations as $language => $text) {
            if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,34}$/', (string) $language)) {
                throw IntegrityViolation::make("[{$language}] is not a valid language tag.");
            }

            $example->translations()->create(['written_in' => (string) $language, 'text' => Text::require($text, 'translation', 5000)]);
        }

        if ($source = $this->references->source($data->sourceId)) {
            $this->attest->store($example, $source);
        }

        return $example;
    }
}
