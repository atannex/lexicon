<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Console;

use Atannex\Lexicon\Models\Language;

final class LanguagesCommand extends TenantAwareCommand
{
    protected $signature = 'lexicon:languages {--all : Include inactive languages}';

    protected $description = 'List the languages visible to the current tenant';

    protected function perform(): int
    {
        $languages = Language::query()
            ->when(! $this->option('all'), fn ($q) => $q->where('is_active', true))
            ->withCount(['varieties', 'lexemes'])
            ->orderBy('code')
            ->get();

        if ($languages->isEmpty()) {
            $this->components->warn('No languages are registered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Code', 'Name', 'Native name', 'ISO 639-3', 'Scope', 'Varieties', 'Entries', 'Status'],
            $languages->map(fn (Language $l): array => [
                $l->code, $l->name, $l->native_name ?? '', $l->iso639_3 ?? '',
                $l->isShared() ? 'shared' : $l->tenant,
                $l->getAttribute('varieties_count'), $l->getAttribute('lexemes_count'),
                $l->documentation_status->value,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
