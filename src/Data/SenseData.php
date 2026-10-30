<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

final readonly class SenseData extends Data
{
    /**
     * @param  list<DefinitionData>  $definitions
     * @param  list<ExampleData>  $examples
     * @param  list<EquivalentData>  $equivalents
     * @param  list<string>  $labels  usage and domain labels
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $definitions = [],
        public array $examples = [],
        public array $equivalents = [],
        public ?string $semanticDomain = null,
        public array $labels = [],
        public ?string $usageNote = null,
        public array $metadata = [],
        public int|string|null $sourceId = null,
    ) {}

    public static function rules(): array
    {
        return [
            'definitions' => ['nullable', 'array', 'max:50'],
            'definitions.*' => ['array'],
            'examples' => ['nullable', 'array', 'max:100'],
            'examples.*' => ['array'],
            'equivalents' => ['nullable', 'array', 'max:100'],
            'equivalents.*' => ['array'],
            'semantic_domain' => ['nullable', 'string', 'max:128'],
            'labels' => ['nullable', 'array', 'max:20'],
            'labels.*' => ['string', 'max:64'],
            'usage_note' => self::text(required: false),
            'metadata' => ['nullable', 'array'],
            'source_id' => ['nullable'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        /** @var list<string> $labels */
        $labels = array_values(self::map($v, 'labels') ?? []);

        return new self(
            definitions: array_map(DefinitionData::fromArray(...), self::rows($v, 'definitions')),
            examples: array_map(ExampleData::fromArray(...), self::rows($v, 'examples')),
            equivalents: array_map(EquivalentData::fromArray(...), self::rows($v, 'equivalents')),
            semanticDomain: self::string($v, 'semantic_domain'),
            labels: $labels,
            usageNote: self::string($v, 'usage_note'),
            metadata: self::map($v, 'metadata') ?? [],
            sourceId: self::string($v, 'source_id'),
        );
    }
}
