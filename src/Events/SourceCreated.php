<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Events;

use Atannex\Lexicon\Models\Source;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class SourceCreated implements ShouldDispatchAfterCommit
{
    public function __construct(public Source $source) {}
}
