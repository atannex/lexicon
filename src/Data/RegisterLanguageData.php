<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Atannex\Lexicon\Enums\DocumentationStatus;
use Illuminate\Validation\Rule;

/**
 * A new language identity. iso639_3 must be a code you have verified
 * against the ISO 639-3 registry; leave it null rather than guess.
 */
final readonly class RegisterLanguageData extends Data
{
    /**
     * @param  list<LanguageNameData>  $alternativeNames
     * @param  array<string, string>  $externalIds  e.g. ['glottolog' => 'abcd1234']
     * @param  array<string, mixed>  $geography
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $nativeName = null,
        public ?string $iso639_3 = null,
        public array $alternativeNames = [],
        public ?string $description = null,
        public array $geography = [],
        public DocumentationStatus $documentationStatus = DocumentationStatus::Undocumented,
        public array $externalIds = [],
        public array $metadata = [],
        public bool $shared = false,
    ) {}

    public static function rules(): array
    {
        return [
            'code' => self::code(),
            'name' => self::text(500),
            'native_name' => self::text(500, false),
            'iso639_3' => ['nullable', 'string', 'regex:/^[a-z]{3}$/'],
            'alternative_names' => ['nullable', 'array', 'max:100'],
            'alternative_names.*' => ['array'],
            'description' => self::text(required: false),
            'geography' => ['nullable', 'array'],
            'documentation_status' => ['nullable', Rule::enum(DocumentationStatus::class)],
            'external_ids' => ['nullable', 'array'],
            'external_ids.*' => ['string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'shared' => ['nullable', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        /** @var array<string, string> $externalIds */
        $externalIds = self::map($v, 'external_ids') ?? [];

        return new self(
            code: (string) self::string($v, 'code'),
            name: (string) self::string($v, 'name'),
            nativeName: self::string($v, 'native_name'),
            iso639_3: self::string($v, 'iso639_3'),
            alternativeNames: array_map(LanguageNameData::fromArray(...), self::rows($v, 'alternative_names')),
            description: self::string($v, 'description'),
            geography: self::map($v, 'geography') ?? [],
            documentationStatus: DocumentationStatus::tryFrom((string) self::string($v, 'documentation_status')) ?? DocumentationStatus::Undocumented,
            externalIds: $externalIds,
            metadata: self::map($v, 'metadata') ?? [],
            shared: (bool) ($v['shared'] ?? false),
        );
    }
}
