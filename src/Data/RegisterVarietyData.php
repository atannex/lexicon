<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Atannex\Lexicon\Enums\VarietyKind;
use Illuminate\Validation\Rule;

final readonly class RegisterVarietyData extends Data
{
    /**
     * @param  list<string>  $alternativeNames
     * @param  array<string, mixed>  $geography
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $code,
        public string $name,
        public VarietyKind $kind = VarietyKind::Dialect,
        public ?string $nativeName = null,
        public array $alternativeNames = [],
        public ?string $description = null,
        public array $geography = [],
        public array $metadata = [],
    ) {}

    public static function rules(): array
    {
        return [
            'code' => self::code(),
            'name' => self::text(500),
            'kind' => ['nullable', Rule::enum(VarietyKind::class)],
            'native_name' => self::text(500, false),
            'alternative_names' => ['nullable', 'array', 'max:100'],
            'alternative_names.*' => self::text(500),
            'description' => self::text(required: false),
            'geography' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        /** @var list<string> $names */
        $names = array_values(self::map($v, 'alternative_names') ?? []);

        return new self(
            code: (string) self::string($v, 'code'),
            name: (string) self::string($v, 'name'),
            kind: VarietyKind::tryFrom((string) self::string($v, 'kind')) ?? VarietyKind::Dialect,
            nativeName: self::string($v, 'native_name'),
            alternativeNames: $names,
            description: self::string($v, 'description'),
            geography: self::map($v, 'geography') ?? [],
            metadata: self::map($v, 'metadata') ?? [],
        );
    }
}
