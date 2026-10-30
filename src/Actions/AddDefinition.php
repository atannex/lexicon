<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\DefinitionData;
use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Models\Definition;
use Atannex\Lexicon\Models\Sense;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Support\Text;
use Illuminate\Contracts\Events\Dispatcher;

final class AddDefinition extends Action
{
    public function __construct(private readonly References $references, private readonly Attest $attest, private readonly Dispatcher $events) {}

    public function handle(Sense $sense, DefinitionData $data, ?string $actor = null): Definition
    {
        $definition = $this->transaction(fn (): Definition => $this->store($sense, $data));

        $this->events->dispatch(new LexemeUpdated($sense->lexeme, 'definition', $actor));

        return $definition;
    }

    /** @internal */
    public function store(Sense $sense, DefinitionData $data): Definition
    {
        $this->references->assertOwned($sense->lexeme);

        $definition = new Definition([
            'written_in' => $data->writtenIn,
            'text' => Text::require($data->text, 'definition', 10000),
            'position' => $sense->definitions()->count(),
        ]);
        $definition->sense()->associate($sense);
        $definition->lexeme_id = $sense->lexeme_id;
        $definition->save();

        if ($source = $this->references->source($data->sourceId)) {
            $this->attest->store($definition, $source);
        }

        return $definition;
    }
}
