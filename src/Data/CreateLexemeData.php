<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Enums\Visibility;
use Illuminate\Validation\Rule;

/**
 * A new dictionary entry. $lemma becomes the citation form; $forms adds
 * further spellings, $senses the meanings. $sourceId (a source id or uid)
 * attests the lemma, so the entry is traceable from the start.
 *
 * $partOfSpeech is free text on purpose: the lexicon does not impose an
 * English category set on languages whose grammar differs.
 */
final readonly class CreateLexemeData extends Data
{
    /**
     * @param  list<FormData>  $forms
     * @param  list<SenseData>  $senses
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $lemma,
        public ?string $partOfSpeech = null,
        public int|string|null $sourceId = null,
        public ?string $orthography = null,
        public ?string $variety = null,
        public ?int $homograph = null,
        public array $forms = [],
        public array $senses = [],
        public ?EntryStatus $status = null,
        public ?Visibility $visibility = null,
        public int|string|null $contributorId = null,
        public array $metadata = [],
        public ?string $locator = null,
    ) {}

    public static function rules(): array
    {
        return [
            'lemma' => self::text(FormData::MAX_LENGTH),
            'part_of_speech' => ['nullable', 'string', 'max:64'],
            'source_id' => ['nullable'],
            'orthography' => self::code(false),
            'variety' => self::code(false),
            'homograph' => ['nullable', 'integer', 'min:1', 'max:999'],
            'forms' => ['nullable', 'array', 'max:200'],
            'forms.*' => ['array'],
            'senses' => ['nullable', 'array', 'max:100'],
            'senses.*' => ['array'],
            'status' => ['nullable', Rule::enum(EntryStatus::class)->only([EntryStatus::Draft, EntryStatus::Submitted])],
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
            'contributor_id' => ['nullable'],
            'metadata' => ['nullable', 'array'],
            'locator' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        return new self(
            lemma: (string) self::string($v, 'lemma'),
            partOfSpeech: self::string($v, 'part_of_speech'),
            sourceId: self::string($v, 'source_id'),
            orthography: self::string($v, 'orthography'),
            variety: self::string($v, 'variety'),
            homograph: isset($v['homograph']) ? (int) $v['homograph'] : null,
            forms: array_map(FormData::fromArray(...), self::rows($v, 'forms')),
            senses: array_map(SenseData::fromArray(...), self::rows($v, 'senses')),
            status: EntryStatus::tryFrom((string) self::string($v, 'status')),
            visibility: Visibility::tryFrom((string) self::string($v, 'visibility')),
            contributorId: self::string($v, 'contributor_id'),
            metadata: self::map($v, 'metadata') ?? [],
            locator: self::string($v, 'locator'),
        );
    }
}
