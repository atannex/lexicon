<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Events;

use Atannex\Lexicon\Models\Lexeme;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class LexemeCreated implements ShouldDispatchAfterCommit
{
    public function __construct(public Lexeme $lexeme) {}
}
