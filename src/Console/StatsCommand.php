<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Console;

use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Models\Definition;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Sense;
use Atannex\Lexicon\Models\Source;

/** Counts are for the current tenant only. */
final class StatsCommand extends TenantAwareCommand
{
    protected $signature = 'lexicon:stats {language? : A language code; omit for every language}';

    protected $description = 'Show entry, form, sense, definition and source counts';

    protected function perform(): int
    {
        $code = $this->argument('language');
        $languages = Language::query()->when(is_string($code), fn ($q) => $q->where('code', $code))->orderBy('code')->get();

        if (is_string($code) && $languages->isEmpty()) {
            $this->components->error("No language with code [{$code}].");

            return self::FAILURE;
        }

        $rows = [];

        foreach ($languages as $language) {
            $entries = Lexeme::query()->where('language_id', $language->id);
            $ids = (clone $entries)->select('id');
            $byStatus = (clone $entries)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

            $rows[] = [
                $language->code,
                (clone $entries)->count(),
                (int) ($byStatus[EntryStatus::Published->value] ?? 0),
                (int) ($byStatus[EntryStatus::Draft->value] ?? 0),
                Form::query()->whereIn('lexeme_id', $ids)->count(),
                Sense::query()->whereIn('lexeme_id', $ids)->count(),
                Definition::query()->whereIn('lexeme_id', $ids)->count(),
            ];
        }

        $this->table(['Language', 'Entries', 'Published', 'Drafts', 'Forms', 'Senses', 'Definitions'], $rows);
        $this->line('Sources: '.Source::query()->count());

        return self::SUCCESS;
    }
}
