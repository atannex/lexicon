<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\LanguageNameData;
use Atannex\Lexicon\Data\RegisterLanguageData;
use Atannex\Lexicon\Events\LanguageRegistered;
use Atannex\Lexicon\Exceptions\DuplicateRecord;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Support\Text;
use Atannex\Lexicon\Tenancy\TenantContext;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Registers a language identity. A code must be unambiguous for every
 * tenant that can see it: a shared language may not reuse any tenant's
 * code, and a tenant may not reuse a shared one.
 */
final class RegisterLanguage extends Action
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly References $references,
        private readonly Dispatcher $events,
    ) {}

    public function handle(RegisterLanguageData $data): Language
    {
        $tenant = $data->shared ? TenantContext::SHARED : $this->tenants->current();

        $taken = Language::query()->withoutGlobalScopes()->where('code', $data->code)
            ->when($tenant !== TenantContext::SHARED, fn ($q) => $q->whereIn('tenant', [TenantContext::SHARED, $tenant]))
            ->exists();

        if ($taken) {
            throw DuplicateRecord::languageCode($data->code);
        }

        if ($data->iso639_3 !== null && ! preg_match('/^[a-z]{3}$/', $data->iso639_3)) {
            throw IntegrityViolation::make('An ISO 639-3 identifier is exactly three lowercase letters.');
        }

        $language = $this->transaction(function () use ($data, $tenant): Language {
            $language = new Language([
                'code' => $data->code,
                'name' => Text::require($data->name, 'name', 500),
                'native_name' => Text::optional($data->nativeName, 'native name', 500),
                'iso639_3' => $data->iso639_3,
                'external_ids' => $data->externalIds ?: null,
                'description' => Text::optional($data->description, 'description'),
                'geography' => $data->geography ?: null,
                'documentation_status' => $data->documentationStatus,
                'metadata' => $data->metadata ?: null,
            ]);
            $language->tenant = $tenant;
            $language->save();

            foreach ($data->alternativeNames as $name) {
                $this->addName($language, $name);
            }

            return $language;
        });

        $this->events->dispatch(new LanguageRegistered($language));

        return $language;
    }

    private function addName(Language $language, LanguageNameData $name): void
    {
        $language->names()->create([
            'name' => Text::require($name->name, 'alternative name', 500),
            'kind' => $name->kind,
            'written_in' => $name->writtenIn,
            'source_id' => $this->references->source($name->sourceId)?->id,
            'notes' => $name->notes,
        ]);
    }
}
