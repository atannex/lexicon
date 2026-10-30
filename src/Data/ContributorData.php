<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Data;

use Atannex\Lexicon\Enums\ConsentStatus;
use Illuminate\Validation\Rule;

final readonly class ContributorData extends Data
{
    /**
     * @param  list<string>  $roles  e.g. speaker, transcriber, reviewer, linguist, custodian
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $displayName,
        public string|int|null $userId = null,
        public ?string $creditAs = null,
        public bool $creditPublicly = false,
        public array $roles = [],
        public ConsentStatus $consent = ConsentStatus::Unknown,
        public array $metadata = [],
    ) {}

    public static function rules(): array
    {
        return [
            'display_name' => self::text(500),
            'user_id' => ['nullable', 'max:64'],
            'credit_as' => self::text(500, false),
            'credit_publicly' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array', 'max:20'],
            'roles.*' => ['string', 'max:32'],
            'consent' => ['nullable', Rule::enum(ConsentStatus::class)],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $v = self::validated($input);

        /** @var list<string> $roles */
        $roles = array_values(self::map($v, 'roles') ?? []);

        return new self(
            displayName: (string) self::string($v, 'display_name'),
            userId: self::string($v, 'user_id'),
            creditAs: self::string($v, 'credit_as'),
            creditPublicly: (bool) ($v['credit_publicly'] ?? false),
            roles: $roles,
            consent: ConsentStatus::tryFrom((string) self::string($v, 'consent')) ?? ConsentStatus::Unknown,
            metadata: self::map($v, 'metadata') ?? [],
        );
    }
}
