<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** Carries identifiers only: the entry no longer exists. */
final readonly class LexemeDeleted implements ShouldDispatchAfterCommit
{
    public function __construct(public string $uid, public string $tenant, public int $languageId, public ?string $lemma, public ?string $actor = null) {}
}
