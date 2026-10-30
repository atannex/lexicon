<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Atannex\Lexicon\Enums\ConsentStatus;
use Atannex\Lexicon\Enums\SourceKind;
use Atannex\Lexicon\Enums\Visibility;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

final readonly class CreateSourceData extends Data
{
    /**
     * @param  list<string>  $authors
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public SourceKind $kind,
        public string $title,
        public ?string $citation = null,
        public array $authors = [],
        public ?string $publisher = null,
        public ?string $published = null,
        public ?string $url = null,
        public ?DateTimeInterface $collectedOn = null,
        public ?string $location = null,
        public ?string $license = null,
        public ConsentStatus $consent = ConsentStatus::Unknown,
        public Visibility $visibility = Visibility::Private,
        public int|string|null $contributorId = null,
        public ?string $notes = null,
        public array $metadata = [],
    ) {}

    public static function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(SourceKind::class)],
            'title' => self::text(1000),
            'citation' => self::text(required: false),
            'authors' => ['nullable', 'array', 'max:50'],
            'authors.*' => self::text(500),
            'publisher' => self::text(500, false),
            'published' => ['nullable', 'string', 'max:32'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'collected_on' => ['nullable', 'date'],
            'location' => self::text(500, false),
            'license' => ['nullable', 'string', 'max:100'],
            'consent' => ['nullable', Rule::enum(ConsentStatus::class)],
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
            'contributor_id' => ['nullable'],
            'notes' => self::text(required: false),
            'metadata' => ['nullable', 'array'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        /** @var list<string> $authors */
        $authors = array_values(self::map($v, 'authors') ?? []);
        $collected = self::string($v, 'collected_on');

        return new self(
            kind: SourceKind::from((string) self::string($v, 'kind')),
            title: (string) self::string($v, 'title'),
            citation: self::string($v, 'citation'),
            authors: $authors,
            publisher: self::string($v, 'publisher'),
            published: self::string($v, 'published'),
            url: self::string($v, 'url'),
            collectedOn: $collected === null ? null : Carbon::parse($collected),
            location: self::string($v, 'location'),
            license: self::string($v, 'license'),
            consent: ConsentStatus::tryFrom((string) self::string($v, 'consent')) ?? ConsentStatus::Unknown,
            visibility: Visibility::tryFrom((string) self::string($v, 'visibility')) ?? Visibility::Private,
            contributorId: self::string($v, 'contributor_id'),
            notes: self::string($v, 'notes'),
            metadata: self::map($v, 'metadata') ?? [],
        );
    }
}
