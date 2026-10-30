<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\EquivalentData;
use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Sense;
use Atannex\Lexicon\Models\TranslationEquivalent;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Support\Text;
use Illuminate\Contracts\Events\Dispatcher;

final class AddTranslationEquivalent extends Action
{
    public function __construct(private readonly References $references, private readonly Dispatcher $events) {}

    public function handle(Sense $sense, EquivalentData $data, ?string $actor = null): TranslationEquivalent
    {
        $equivalent = $this->transaction(fn (): TranslationEquivalent => $this->store($sense, $data));

        $this->events->dispatch(new LexemeUpdated($sense->lexeme, 'equivalent', $actor));

        return $equivalent;
    }

    /** @internal */
    public function store(Sense $sense, EquivalentData $data): TranslationEquivalent
    {
        $this->references->assertOwned($sense->lexeme);

        if ($data->text === null && $data->targetSenseId === null) {
            throw IntegrityViolation::make('A translation equivalent needs text, a target sense, or both.');
        }

        $target = null;

        if ($data->targetSenseId !== null) {
            // The target must be an entry this tenant can see.
            $target = Sense::query()->whereKey($data->targetSenseId)->whereIn('lexeme_id', Lexeme::query()->select('id'))->first()
                ?? throw IntegrityViolation::make("Sense [{$data->targetSenseId}] does not exist.");

            if ($target->is($sense)) {
                throw IntegrityViolation::make('A sense cannot be its own translation.');
            }
        }

        $equivalent = new TranslationEquivalent([
            'written_in' => $data->writtenIn,
            'text' => Text::optional($data->text, 'translation', 1000),
            'type' => $data->type,
            'notes' => $data->notes,
            'position' => $sense->equivalents()->count(),
        ]);
        $equivalent->sense()->associate($sense);
        $equivalent->target()->associate($target);
        $equivalent->save();

        return $equivalent;
    }
}
