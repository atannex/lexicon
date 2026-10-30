<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Contracts;

/**
 * An add-on that announces what it provides. Ship it as an ordinary
 * Laravel package: its service provider registers services, routes and
 * commands as usual, and tags this object with LexiconServiceProvider::
 * TAG_EXTENSIONS so other code can ask Lexicon::supports('capability').
 */
interface Extension
{
    public function name(): string;

    /** @return list<string> capability names, e.g. ['pronunciation', 'media.audio'] */
    public function capabilities(): array;
}
