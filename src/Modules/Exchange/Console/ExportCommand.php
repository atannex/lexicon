<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Exchange\Console;

use Atannex\Lexicon\Authorization\ReadAccess;
use Atannex\Lexicon\Console\TenantAwareCommand;
use Atannex\Lexicon\Enums\Visibility;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Modules\Exchange\Exporter;
use Atannex\Lexicon\Modules\Exchange\Format;

/**
 * Exports published, public entries unless told otherwise. Wider exports
 * need explicit flags, because the operator decides what leaves the system.
 */
final class ExportCommand extends TenantAwareCommand
{
    protected $signature = 'lexicon:export
        {language : Language code}
        {path : File to write; must not exist unless --force}
        {--format=jsonl : jsonl, json or csv}
        {--include-unpublished : Include drafts, submissions and archived entries}
        {--include=public : Comma-separated visibilities to include (public,community,restricted,private)}
        {--force : Overwrite the file if it exists}';

    protected $description = 'Export a language\'s entries to JSON Lines, JSON or CSV';

    protected function perform(): int
    {
        $language = Language::query()->where('code', $this->text('language'))->first();
        $format = Format::tryFrom((string) $this->textOption('format'));
        $path = $this->text('path');

        if ($language === null || $format === null) {
            $this->components->error($language === null ? 'Unknown language.' : 'Unknown format; use jsonl, json or csv.');

            return self::FAILURE;
        }

        $visibilities = [];

        foreach (explode(',', (string) $this->textOption('include')) as $name) {
            $visibility = Visibility::tryFrom(trim($name));

            if ($visibility === null) {
                $this->components->error("Unknown visibility [{$name}].");

                return self::FAILURE;
            }

            $visibilities[] = $visibility;
        }

        if (file_exists($path) && ! $this->option('force')) {
            $this->components->error("[{$path}] exists; pass --force to overwrite it.");

            return self::FAILURE;
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            $this->components->error("Cannot write [{$path}].");

            return self::FAILURE;
        }

        try {
            $count = $this->laravel->make(Exporter::class)->export($language, $handle, $format, new ReadAccess($visibilities, (bool) $this->option('include-unpublished')));
        } finally {
            fclose($handle);
        }

        $this->components->info("Exported {$count} entries to {$path}.");

        return self::SUCCESS;
    }
}
