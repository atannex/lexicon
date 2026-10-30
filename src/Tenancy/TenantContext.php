<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Tenancy;

use Atannex\Lexicon\Contracts\TenantResolver;
use Closure;
use InvalidArgumentException;

/**
 * The tenant every lexicon query and write is scoped to. Normally this is
 * whatever the TenantResolver reports; commands and queued jobs, which run
 * outside a request, switch it explicitly with run().
 *
 * Keys are strings and '' is the shared scope. A non-null sentinel keeps
 * unique indexes portable, since NULLs never compare equal in SQL.
 */
final class TenantContext
{
    public const string SHARED = '';

    /** @var list<string> */
    private array $stack = [];

    public function __construct(private readonly TenantResolver $resolver) {}

    public function current(): string
    {
        if ($this->stack !== []) {
            return $this->stack[array_key_last($this->stack)];
        }

        return self::normalise($this->resolver->current());
    }

    public function isShared(): bool
    {
        return $this->current() === self::SHARED;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(string|int|null $tenant, Closure $callback): mixed
    {
        $this->stack[] = self::normalise($tenant);

        try {
            return $callback();
        } finally {
            array_pop($this->stack);
        }
    }

    public static function normalise(string|int|null $tenant): string
    {
        $key = $tenant === null ? self::SHARED : (string) $tenant;

        if ($key !== self::SHARED && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/', $key)) {
            throw new InvalidArgumentException("[{$key}] is not a valid tenant key.");
        }

        return $key;
    }
}
