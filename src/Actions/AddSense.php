<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\SenseData;
use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Sense;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Support\Text;
use Illuminate\Contracts\Events\Dispatcher;

final class AddSense extends Action
{
    public function __construct(
        private readonly References $references,
        private readonly AddDefinition $addDefinition,
        private readonly AddExample $addExample,
        private readonly AddTranslationEquivalent $addEquivalent,
        private readonly Attest $attest,
        private readonly Dispatcher $events,
    ) {}

    public function handle(Lexeme $lexeme, SenseData $data, ?string $actor = null): Sense
    {
        $sense = $this->transaction(fn (): Sense => $this->store($lexeme, $data));

        $this->events->dispatch(new LexemeUpdated($lexeme, 'sense', $actor));

        return $sense;
    }

    /** @internal used inside CreateLexeme's transaction */
    public function store(Lexeme $lexeme, SenseData $data, ?int $position = null): Sense
    {
        $this->references->assertOwned($lexeme);

        $sense = new Sense([
            'position' => $position ?? ((int) $lexeme->senses()->max('position') + ($lexeme->senses()->exists() ? 1 : 0)),
            'semantic_domain' => $data->semanticDomain,
            'labels' => $data->labels ?: null,
            'usage_note' => Text::optional($data->usageNote, 'usage note'),
            'metadata' => $data->metadata ?: null,
        ]);
        $sense->lexeme()->associate($lexeme);
        $sense->save();

        foreach ($data->definitions as $definition) {
            $this->addDefinition->store($sense, $definition);
        }

        foreach ($data->examples as $example) {
            $this->addExample->store($sense, $example);
        }

        foreach ($data->equivalents as $equivalent) {
            $this->addEquivalent->store($sense, $equivalent);
        }

        if ($source = $this->references->source($data->sourceId)) {
            $this->attest->store($sense, $source);
        }

        return $sense;
    }
}
