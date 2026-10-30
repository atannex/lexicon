<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Support;

use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Contributor;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Orthography;
use Atannex\Lexicon\Models\Source;
use Atannex\Lexicon\Models\Variety;
use Atannex\Lexicon\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves references inside write input. Every lookup runs through the
 * tenant scope, so input can never point at another tenant's records.
 */
final readonly class References
{
    public function __construct(private TenantContext $tenants) {}

    public function source(int|string|null $id): ?Source
    {
        return $id === null ? null : ($this->byIdOrUid(Source::class, $id) ?? throw IntegrityViolation::make("Source [{$id}] does not exist."));
    }

    public function contributor(int|string|null $id): ?Contributor
    {
        return $id === null ? null : ($this->byIdOrUid(Contributor::class, $id) ?? throw IntegrityViolation::make("Contributor [{$id}] does not exist."));
    }

    public function variety(Language $language, ?string $code): ?Variety
    {
        if ($code === null) {
            return null;
        }

        return Variety::query()->where('language_id', $language->id)->where('code', $code)->first()
            ?? throw IntegrityViolation::make("Language [{$language->code}] has no variety [{$code}].");
    }

    /** An orthography of the language, or a notation shared by all languages (IPA). */
    public function orthography(Language $language, ?string $code): ?Orthography
    {
        if ($code === null) {
            return null;
        }

        return Orthography::query()
            ->where('code', $code)
            ->where(fn ($q) => $q->where('language_id', $language->id)->orWhereNull('language_id'))
            ->orderByRaw('language_id is null')
            ->first()
            ?? throw IntegrityViolation::make("Language [{$language->code}] has no orthography [{$code}].");
    }

    /** Fail when a model loaded outside the tenant scope is passed to a write. */
    public function assertOwned(Lexeme|Source|Contributor $model): void
    {
        if ($model->tenant !== $this->tenants->current()) {
            throw IntegrityViolation::make('This record belongs to another tenant.');
        }
    }

    public function assertVisible(Language $language): void
    {
        if (! $language->isShared() && $language->tenant !== $this->tenants->current()) {
            throw IntegrityViolation::make('This language belongs to another tenant.');
        }
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    private function byIdOrUid(string $class, int|string $id): ?Model
    {
        $query = $class::query();

        return is_int($id) || ctype_digit($id) ? $query->whereKey((int) $id)->first() : $query->where('uid', $id)->first();
    }
}
