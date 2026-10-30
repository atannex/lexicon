<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Illuminate\Support\Facades\Validator;

/**
 * Typed input for a lexicon write. Build one with named arguments from
 * already-validated input, or with fromArray() to validate raw input
 * (HTTP payloads, import rows) against rules().
 */
abstract readonly class Data
{
    /** @return array<string, mixed> Laravel validation rules for fromArray() input */
    abstract public static function rules(): array;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected static function validated(array $input): array
    {
        /** @var array<string, mixed> */
        return Validator::make($input, static::rules())->validate();
    }

    /** @param array<string, mixed> $input */
    protected static function string(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    protected static function map(array $input, string $key): ?array
    {
        $value = $input[$key] ?? null;

        /** @var array<string, mixed>|null */
        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    protected static function rows(array $input, string $key): array
    {
        $value = $input[$key] ?? [];

        /** @var list<array<string, mixed>> */
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /**
     * Rules that accept any valid UTF-8 text but reject control characters.
     *
     * @return list<string>
     */
    public static function text(int $max = 65535, bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', "max:{$max}", 'not_regex:/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u'];
    }

    /**
     * A language tag for text: a BCP 47 tag ("en", "fr-CM") or a lexicon language code.
     *
     * @return list<string>
     */
    public static function languageTag(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:35', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]{0,34}$/'];
    }

    /** @return list<string> */
    public static function code(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]{0,63}$/'];
    }
}
