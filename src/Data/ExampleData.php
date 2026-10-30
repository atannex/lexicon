<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

final readonly class ExampleData extends Data
{
    /** @param array<string, string> $translations language tag => translated text */
    public function __construct(
        public string $text,
        public array $translations = [],
        public ?string $context = null,
        public ?string $variety = null,
        public ?string $orthography = null,
        public ?string $audio = null,
        public int|string|null $sourceId = null,
    ) {}

    public static function rules(): array
    {
        return [
            'text' => self::text(5000),
            'translations' => ['nullable', 'array', 'max:50'],
            'translations.*' => self::text(5000),
            'context' => self::text(required: false),
            'variety' => self::code(false),
            'orthography' => self::code(false),
            'audio' => ['nullable', 'string', 'max:1024'],
            'source_id' => ['nullable'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        /** @var array<string, string> $translations */
        $translations = self::map($v, 'translations') ?? [];

        return new self(
            text: (string) self::string($v, 'text'),
            translations: $translations,
            context: self::string($v, 'context'),
            variety: self::string($v, 'variety'),
            orthography: self::string($v, 'orthography'),
            audio: self::string($v, 'audio'),
            sourceId: self::string($v, 'source_id'),
        );
    }
}
