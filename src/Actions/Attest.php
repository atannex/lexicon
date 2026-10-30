<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Attestation;
use Atannex\Lexicon\Models\Definition;
use Atannex\Lexicon\Models\Example;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Sense;
use Atannex\Lexicon\Models\Source;
use Atannex\Lexicon\Models\TranslationEquivalent;
use Atannex\Lexicon\Support\References;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Records that a source supports a claim: an entry, a form, a sense, a
 * definition, an example or a translation.
 */
final class Attest extends Action
{
    public function __construct(private readonly References $references, private readonly Dispatcher $events) {}

    public function handle(
        Lexeme|Form|Sense|Definition|Example|TranslationEquivalent $claim,
        Source|int|string $source,
        ?string $locator = null,
        ?string $quotation = null,
        ?string $notes = null,
        int|string|null $contributorId = null,
        ?string $actor = null,
    ): Attestation {
        $source = $source instanceof Source ? $source : $this->references->source($source);
        assert($source instanceof Source);

        $attestation = $this->store($claim, $source, $locator, $quotation, $notes, $this->references->contributor($contributorId)?->id);

        if ($lexeme = $this->lexemeOf($claim)) {
            $this->events->dispatch(new LexemeUpdated($lexeme, 'attestation', $actor));
        }

        return $attestation;
    }

    /** @internal */
    public function store(
        Lexeme|Form|Sense|Definition|Example|TranslationEquivalent $claim,
        Source $source,
        ?string $locator = null,
        ?string $quotation = null,
        ?string $notes = null,
        ?int $contributorId = null,
    ): Attestation {
        $this->references->assertOwned($source);

        $lexeme = $this->lexemeOf($claim);

        if ($lexeme !== null) {
            $this->references->assertOwned($lexeme);
        }

        if ($locator !== null && mb_strlen($locator) > 255) {
            throw IntegrityViolation::make('A locator is at most 255 characters.');
        }

        $attestation = new Attestation(['locator' => $locator, 'quotation' => $quotation, 'notes' => $notes, 'contributor_id' => $contributorId]);
        $attestation->source()->associate($source);
        $attestation->attestable()->associate($claim);
        $attestation->lexeme_id = $lexeme?->id;
        $attestation->tenant = $source->tenant;
        $attestation->save();

        return $attestation;
    }

    private function lexemeOf(Lexeme|Form|Sense|Definition|Example|TranslationEquivalent $claim): ?Lexeme
    {
        return match (true) {
            $claim instanceof Lexeme => $claim,
            $claim instanceof TranslationEquivalent => $claim->sense->lexeme,
            default => Lexeme::query()->find($claim->lexeme_id),
        };
    }
}
