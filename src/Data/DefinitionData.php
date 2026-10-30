<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

final readonly class DefinitionData extends Data
{
    public function __construct(
        public string $text,
        public string $writtenIn,
        public int|string|null $sourceId = null,
    ) {}

    public static function rules(): array
    {
        return [
            'text' => self::text(10000),
            'written_in' => self::languageTag(),
            'source_id' => ['nullable'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        return new self((string) self::string($v, 'text'), (string) self::string($v, 'written_in'), self::string($v, 'source_id'));
    }
}
