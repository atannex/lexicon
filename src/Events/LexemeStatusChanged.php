<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Events;

use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Models\Lexeme;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class LexemeStatusChanged implements ShouldDispatchAfterCommit
{
    public function __construct(public Lexeme $lexeme, public EntryStatus $from, public EntryStatus $to, public ?string $actor = null) {}
}
