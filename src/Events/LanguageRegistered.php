<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Events;

use Atannex\Lexicon\Models\Language;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class LanguageRegistered implements ShouldDispatchAfterCommit
{
    public function __construct(public Language $language) {}
}
