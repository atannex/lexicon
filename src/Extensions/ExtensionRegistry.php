<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Extensions;

use Atannex\Lexicon\Contracts\Extension;
use InvalidArgumentException;

final class ExtensionRegistry
{
    /** @var array<string, Extension> */
    private array $extensions = [];

    public function register(Extension $extension): void
    {
        $name = $extension->name();

        if (isset($this->extensions[$name]) && $extension::class !== $this->extensions[$name]::class) {
            throw new InvalidArgumentException("Another extension is already registered as [{$name}].");
        }

        $this->extensions[$name] = $extension;
    }

    public function has(string $name): bool
    {
        return isset($this->extensions[$name]);
    }

    public function supports(string $capability): bool
    {
        foreach ($this->extensions as $extension) {
            if (in_array($capability, $extension->capabilities(), true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, Extension> */
    public function all(): array
    {
        return $this->extensions;
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        $capabilities = [];

        foreach ($this->extensions as $extension) {
            array_push($capabilities, ...$extension->capabilities());
        }

        return array_values(array_unique($capabilities));
    }
}
