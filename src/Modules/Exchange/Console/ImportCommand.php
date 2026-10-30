<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Exchange\Console;

use Atannex\Lexicon\Console\TenantAwareCommand;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Source;
use Atannex\Lexicon\Modules\Exchange\Format;
use Atannex\Lexicon\Modules\Exchange\Importer;

/** Imports entries as drafts. Never overwrites, never publishes. */
final class ImportCommand extends TenantAwareCommand
{
    protected $signature = 'lexicon:import
        {language : Language code}
        {path : File to read}
        {--format=jsonl : jsonl, json or csv}
        {--source= : Source uid that attests every imported lemma}
        {--dry-run : Validate and detect duplicates without writing}';

    protected $description = 'Import entries from JSON Lines, JSON or CSV as drafts';

    protected function perform(): int
    {
        $language = Language::query()->where('code', $this->text('language'))->first();
        $format = Format::tryFrom((string) $this->textOption('format'));
        $sourceUid = $this->textOption('source');
        $source = is_string($sourceUid) ? Source::query()->where('uid', $sourceUid)->first() : null;

        if ($language === null || $format === null || (is_string($sourceUid) && $source === null)) {
            $this->components->error(match (true) {
                $language === null => 'Unknown language.',
                $format === null => 'Unknown format; use jsonl, json or csv.',
                default => "Unknown source [{$sourceUid}].",
            });

            return self::FAILURE;
        }

        try {
            $report = $this->laravel->make(Importer::class)->import($language, $this->text('path'), $format, (bool) $this->option('dry-run'), $source);
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($report->skipped as $record => $reason) {
            $this->line("  <comment>skipped</comment> record {$record}: {$reason}");
        }

        foreach ($report->failed as $record => $errors) {
            $this->line("  <error>failed</error>  record {$record}: ".implode('; ', $errors));
        }

        $verb = $report->dryRun ? 'would create' : 'created';
        $this->line(sprintf('%s %d, skipped %d, failed %d', ucfirst($verb), $report->created, count($report->skipped), count($report->failed)));

        return $report->ok() ? self::SUCCESS : self::FAILURE;
    }
}
