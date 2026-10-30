<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Events;

use Atannex\Lexicon\Models\Lexeme;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Something in the entry changed. $change names what: "fields", "form",
 * "lemma", "sense", "definition", "example", "equivalent", "relation",
 * "attestation".
 */
final readonly class LexemeUpdated implements ShouldDispatchAfterCommit
{
    public function __construct(public Lexeme $lexeme, public string $change, public ?string $actor = null) {}
}
