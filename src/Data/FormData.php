<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Atannex\Lexicon\Enums\FormKind;
use Illuminate\Validation\Rule;

/**
 * One spelling or transcription. $text is stored exactly as given.
 * $orthography and $variety are codes within the entry's language.
 */
final readonly class FormData extends Data
{
    public const int MAX_LENGTH = 200;

    /** @param array<string, mixed> $features grammatical features of an inflected form */
    public function __construct(
        public string $text,
        public FormKind $kind = FormKind::Variant,
        public ?string $orthography = null,
        public ?string $variety = null,
        public array $features = [],
        public int|string|null $sourceId = null,
    ) {}

    public static function rules(): array
    {
        return [
            'text' => self::text(self::MAX_LENGTH),
            'kind' => ['nullable', Rule::enum(FormKind::class)],
            'orthography' => self::code(false),
            'variety' => self::code(false),
            'features' => ['nullable', 'array'],
            'source_id' => ['nullable'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        return new self(
            text: (string) self::string($v, 'text'),
            kind: FormKind::tryFrom((string) self::string($v, 'kind')) ?? FormKind::Variant,
            orthography: self::string($v, 'orthography'),
            variety: self::string($v, 'variety'),
            features: self::map($v, 'features') ?? [],
            sourceId: self::string($v, 'source_id'),
        );
    }
}
