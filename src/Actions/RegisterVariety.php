<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\RegisterVarietyData;
use Atannex\Lexicon\Exceptions\DuplicateRecord;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Variety;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Support\Text;
use Atannex\Lexicon\Tenancy\TenantContext;

/**
 * Documents a variety of a language. A tenant may document its own
 * varieties of a shared language; those stay visible to that tenant only.
 */
final class RegisterVariety extends Action
{
    public function __construct(private readonly TenantContext $tenants, private readonly References $references) {}

    public function handle(Language $language, RegisterVarietyData $data): Variety
    {
        $this->references->assertVisible($language);

        if (Variety::query()->withoutGlobalScopes()->where('language_id', $language->id)->where('code', $data->code)->exists()) {
            throw DuplicateRecord::varietyCode($data->code, $language->code);
        }

        $variety = new Variety([
            'code' => $data->code,
            'name' => Text::require($data->name, 'name', 500),
            'native_name' => Text::optional($data->nativeName, 'native name', 500),
            'alternative_names' => $data->alternativeNames ?: null,
            'kind' => $data->kind,
            'description' => Text::optional($data->description, 'description'),
            'geography' => $data->geography ?: null,
            'metadata' => $data->metadata ?: null,
        ]);
        $variety->tenant = $this->tenants->current();
        $variety->language()->associate($language);
        $variety->save();

        return $variety;
    }
}
