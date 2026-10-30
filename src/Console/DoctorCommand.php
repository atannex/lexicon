<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Console;

use Atannex\Lexicon\Contracts\SearchEngine;
use Atannex\Lexicon\Contracts\TenantResolver;
use Atannex\Lexicon\Extensions\ExtensionRegistry;
use Atannex\Lexicon\Support\Tables;
use Atannex\Lexicon\Support\Unicode;
use Atannex\Lexicon\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Read-only health check. Exits 1 when something needs fixing. */
final class DoctorCommand extends TenantAwareCommand
{
    protected $signature = 'lexicon:doctor';

    protected $description = 'Check the lexicon installation: extensions, database tables, collations, tenancy and search';

    private int $failures = 0;

    protected function perform(): int
    {
        $this->check('ext-intl loaded', extension_loaded('intl'));
        $this->check('Unicode normalisation works', $this->normalises());

        $connection = Tables::connection();
        $schema = Schema::connection($connection);

        foreach (['languages', 'lexemes', 'forms', 'senses', 'definitions', 'sources', 'attestations'] as $table) {
            $this->check('table '.Tables::name($table), $this->safely(fn (): bool => $schema->hasTable(Tables::name($table))));
        }

        if (DB::connection($connection)->getDriverName() === 'mysql') {
            $this->check('search keys use a binary collation', $this->safely(fn (): bool => $this->binaryKeys($connection)));
        }

        $resolver = $this->laravel->make(TenantResolver::class);
        $this->line('  tenant resolver: '.$resolver::class.' (current: "'.$this->laravel->make(TenantContext::class)->current().'")');
        $this->line('  search engine:   '.$this->laravel->make(SearchEngine::class)::class);
        $this->line('  capabilities:    '.implode(', ', $this->laravel->make(ExtensionRegistry::class)->capabilities()));

        if ($this->failures > 0) {
            $this->components->error("{$this->failures} check(s) failed.");

            return self::FAILURE;
        }

        $this->components->info('All checks passed.');

        return self::SUCCESS;
    }

    private function check(string $label, bool $ok): void
    {
        $this->line(($ok ? '  <info>ok</info>   ' : '  <error>fail</error> ').$label);
        $this->failures += $ok ? 0 : 1;
    }

    private function normalises(): bool
    {
        return $this->safely(fn (): bool => Unicode::nfc("a\u{0300}") === "\u{00E0}");
    }

    private function binaryKeys(?string $connection): bool
    {
        $column = DB::connection($connection)->selectOne(
            'select collation_name from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?',
            [Tables::name('forms'), 'key_exact'],
        );

        return is_object($column) && str_ends_with((string) ($column->collation_name ?? $column->COLLATION_NAME ?? ''), '_bin');
    }

    /** @param callable(): bool $check */
    private function safely(callable $check): bool
    {
        try {
            return $check();
        } catch (Throwable) {
            return false;
        }
    }
}
