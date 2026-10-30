<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Extensions;

use Atannex\Lexicon\Contracts\Extension;

/** Describes the core and the bundled modules in the registry. */
final readonly class ModuleExtension implements Extension
{
    /** @param list<string> $capabilities */
    public function __construct(private string $name, private array $capabilities) {}

    public function name(): string
    {
        return $this->name;
    }

    public function capabilities(): array
    {
        return $this->capabilities;
    }
}
