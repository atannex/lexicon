<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Atannex\Lexicon\Enums\EquivalenceType;
use Illuminate\Validation\Rule;

/**
 * A translation of a sense into another language: free text, a link to a
 * sense of an entry in the same lexicon ($targetSenseId), or both.
 */
final readonly class EquivalentData extends Data
{
    public function __construct(
        public string $writtenIn,
        public ?string $text = null,
        public ?int $targetSenseId = null,
        public EquivalenceType $type = EquivalenceType::Exact,
        public ?string $notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'written_in' => self::languageTag(),
            'text' => [...self::text(1000, false), 'required_without:target_sense_id'],
            'target_sense_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(EquivalenceType::class)],
            'notes' => self::text(required: false),
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        return new self(
            writtenIn: (string) self::string($v, 'written_in'),
            text: self::string($v, 'text'),
            targetSenseId: isset($v['target_sense_id']) ? (int) $v['target_sense_id'] : null,
            type: EquivalenceType::tryFrom((string) self::string($v, 'type')) ?? EquivalenceType::Exact,
            notes: self::string($v, 'notes'),
        );
    }
}
