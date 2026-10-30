<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Atannex\Lexicon\Models\LanguageName;
use Illuminate\Validation\Rule;

final readonly class LanguageNameData extends Data
{
    public function __construct(
        public string $name,
        public string $kind = 'alternative',
        public ?string $writtenIn = null,
        public int|string|null $sourceId = null,
        public ?string $notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => self::text(500),
            'kind' => ['nullable', Rule::in(LanguageName::KINDS)],
            'written_in' => self::languageTag(false),
            'source_id' => ['nullable'],
            'notes' => self::text(required: false),
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        return new self((string) self::string($v, 'name'), self::string($v, 'kind') ?? 'alternative', self::string($v, 'written_in'), self::string($v, 'source_id'), self::string($v, 'notes'));
    }
}
