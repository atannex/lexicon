<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Atannex\Lexicon\Enums\Visibility;
use Illuminate\Validation\Rule;

/**
 * Changes to an entry's own fields. Arguments left at Keep::Value are not
 * touched; null clears the field. Spellings and senses have their own
 * actions, and status changes go through ChangeLexemeStatus.
 */
final readonly class UpdateLexemeData extends Data
{
    /** @param array<string, mixed>|Keep $metadata */
    public function __construct(
        public string|null|Keep $partOfSpeech = Keep::Value,
        public int|null|Keep $homograph = Keep::Value,
        public string|null|Keep $variety = Keep::Value,
        public Visibility|Keep $visibility = Keep::Value,
        public array|Keep $metadata = Keep::Value,
    ) {}

    public static function rules(): array
    {
        return [
            'part_of_speech' => ['nullable', 'string', 'max:64'],
            'homograph' => ['nullable', 'integer', 'min:1', 'max:999'],
            'variety' => self::code(false),
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        return new self(
            partOfSpeech: array_key_exists('part_of_speech', $v) ? self::string($v, 'part_of_speech') : Keep::Value,
            homograph: array_key_exists('homograph', $v) ? ($v['homograph'] === null ? null : (int) $v['homograph']) : Keep::Value,
            variety: array_key_exists('variety', $v) ? self::string($v, 'variety') : Keep::Value,
            visibility: array_key_exists('visibility', $v) ? Visibility::from((string) self::string($v, 'visibility')) : Keep::Value,
            metadata: array_key_exists('metadata', $v) ? (self::map($v, 'metadata') ?? []) : Keep::Value,
        );
    }
}
