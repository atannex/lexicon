<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Console;

use Atannex\Lexicon\Models\Definition;
use Atannex\Lexicon\Models\Example;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Orthography\OrthographyRegistry;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recomputes search and sort keys from the stored originals, e.g. after
 * an orthography's rules changed. Originals are never modified.
 */
final class ReindexCommand extends TenantAwareCommand
{
    protected $signature = 'lexicon:reindex {language? : A language code; omit for every language} {--dry-run : Count stale rows without writing}';

    protected $description = 'Recompute search and sort keys for forms, definitions and examples';

    protected function perform(): int
    {
        $code = $this->argument('language');
        $language = is_string($code) ? Language::query()->where('code', $code)->first() : null;

        if (is_string($code) && $language === null) {
            $this->components->error("No language with code [{$code}].");

            return self::FAILURE;
        }

        $this->laravel->make(OrthographyRegistry::class)->flush();
        $ids = Lexeme::query()->when($language, fn ($q, Language $l) => $q->where('language_id', $l->id))->select('id');
        $dry = (bool) $this->option('dry-run');

        $this->report('Form', $this->refresh(Form::query()->whereIn('lexeme_id', clone $ids), static fn (Form $f) => $f->refreshKeys(), $dry), $dry);
        $this->report('Definition', $this->refresh(Definition::query()->whereIn('lexeme_id', clone $ids), static fn (Definition $d) => $d->refreshSearchText(), $dry), $dry);
        $this->report('Example', $this->refresh(Example::query()->whereIn('lexeme_id', clone $ids), static fn (Example $e) => $e->refreshSearchText(), $dry), $dry);

        return self::SUCCESS;
    }

    /** @param Builder<Form>|Builder<Definition>|Builder<Example> $query */
    private function refresh(Builder $query, Closure $refresh, bool $dry): int
    {
        $changed = 0;

        $query->chunkById(500, function ($rows) use ($refresh, $dry, &$changed): void {
            foreach ($rows as $row) {
                $refresh($row);

                if ($row->isDirty()) {
                    $changed++;

                    if (! $dry) {
                        // Keys are already computed; skip the saving hooks.
                        $row->saveQuietly();
                    }
                }
            }
        });

        return $changed;
    }

    private function report(string $label, int $count, bool $dry): void
    {
        $this->line(sprintf('  %-12s %d %s', $label, $count, $dry ? 'stale' : 'updated'));
    }
}
