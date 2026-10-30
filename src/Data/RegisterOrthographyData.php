<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Atannex\Lexicon\Enums\OrthographyKind;
use Illuminate\Validation\Rule;

/**
 * A writing system for a language. $rules follows OrthographyRules
 * (inventory, tone_marks, equivalences, alphabet, transliteration,
 * word_joiners) and must come from the language's own conventions.
 */
final readonly class RegisterOrthographyData extends Data
{
    /** @param array<string, mixed>|null $rules */
    public function __construct(
        public string $code,
        public string $name,
        public OrthographyKind $kind = OrthographyKind::Orthography,
        public string $script = 'Latn',
        public string $direction = 'ltr',
        public ?string $profile = null,
        public ?array $rules = null,
        public bool $isDefault = false,
        public ?string $description = null,
    ) {}

    public static function rules(): array
    {
        return [
            'code' => self::code(),
            'name' => self::text(500),
            'kind' => ['nullable', Rule::enum(OrthographyKind::class)],
            'script' => ['nullable', 'string', 'regex:/^[A-Z][a-z]{3}$/'],
            'direction' => ['nullable', Rule::in(['ltr', 'rtl'])],
            'profile' => ['nullable', 'string', 'max:64'],
            'rules' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
            'description' => self::text(required: false),
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        return new self(
            code: (string) self::string($v, 'code'),
            name: (string) self::string($v, 'name'),
            kind: OrthographyKind::tryFrom((string) self::string($v, 'kind')) ?? OrthographyKind::Orthography,
            script: self::string($v, 'script') ?? 'Latn',
            direction: self::string($v, 'direction') ?? 'ltr',
            profile: self::string($v, 'profile'),
            rules: self::map($v, 'rules'),
            isDefault: (bool) ($v['is_default'] ?? false),
            description: self::string($v, 'description'),
        );
    }
}
