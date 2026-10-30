<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\RegisterOrthographyData;
use Atannex\Lexicon\Exceptions\DuplicateRecord;
use Atannex\Lexicon\Exceptions\InvalidOrthography;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Orthography;
use Atannex\Lexicon\Orthography\OrthographyRegistry;
use Atannex\Lexicon\Orthography\OrthographyRules;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Support\Text;
use Atannex\Lexicon\Tenancy\TenantContext;

/**
 * Registers a writing system. Pass no language for a notation every
 * language can use, such as IPA. Rules are validated before saving, so a
 * malformed rule set cannot break search later.
 */
final class RegisterOrthography extends Action
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly References $references,
        private readonly OrthographyRegistry $registry,
    ) {}

    public function handle(?Language $language, RegisterOrthographyData $data): Orthography
    {
        if ($language !== null) {
            $this->references->assertVisible($language);
        }

        $tenant = $language?->isShared() ? TenantContext::SHARED : $this->tenants->current();

        if ($data->rules !== null) {
            OrthographyRules::fromArray($data->rules);
        }

        if ($data->profile !== null && ! $this->registry->has($data->profile)) {
            throw InvalidOrthography::unknownProfile($data->profile);
        }

        $exists = Orthography::query()->withoutGlobalScopes()
            ->where('tenant', $tenant)
            ->where('code', $data->code)
            ->where(fn ($q) => $language === null ? $q->whereNull('language_id') : $q->where('language_id', $language->id))
            ->exists();

        if ($exists) {
            throw DuplicateRecord::orthographyCode($data->code);
        }

        $orthography = $this->transaction(function () use ($language, $data, $tenant): Orthography {
            $isDefault = $data->isDefault || ($language !== null && ! $language->orthographies()->where('tenant', $tenant)->exists());

            if ($isDefault && $language !== null) {
                $language->orthographies()->where('tenant', $tenant)->update(['is_default' => false]);
            }

            $orthography = new Orthography([
                'code' => $data->code,
                'name' => Text::require($data->name, 'name', 500),
                'kind' => $data->kind,
                'script' => $data->script,
                'direction' => $data->direction,
                'profile' => $data->profile,
                'rules' => $data->rules,
                'is_default' => $isDefault,
                'description' => Text::optional($data->description, 'description'),
            ]);
            $orthography->tenant = $tenant;
            $orthography->language_id = $language?->id;
            $orthography->save();

            return $orthography;
        });

        $this->registry->flush();

        return $orthography;
    }
}
