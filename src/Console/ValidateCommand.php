<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Console;

use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Orthography\OrthographyRegistry;

/**
 * Read-only data check: spellings outside their orthography's inventory,
 * stale search keys, entries without a lemma, published entries without a
 * sense. Exits 1 when it finds problems.
 */
final class ValidateCommand extends TenantAwareCommand
{
    protected $signature = 'lexicon:validate {language? : A language code; omit for every language} {--limit=50 : Problems to print per check}';

    protected $description = 'Check lexicon data against orthography inventories and structural rules';

    protected function perform(): int
    {
        $code = $this->argument('language');
        $language = is_string($code) ? Language::query()->where('code', $code)->first() : null;

        if (is_string($code) && $language === null) {
            $this->components->error("No language with code [{$code}].");

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $registry = $this->laravel->make(OrthographyRegistry::class);
        $problems = 0;

        $entries = Lexeme::query()->when($language, fn ($q, Language $l) => $q->where('language_id', $l->id));

        Form::query()
            ->whereIn('lexeme_id', (clone $entries)->select('id'))
            ->with('lexeme:id,uid')
            ->chunkById(500, function ($forms) use ($registry, &$problems, $limit): void {
                foreach ($forms as $form) {
                    $profile = $registry->forOrthographyId($form->orthography_id, $form->language_id);
                    $bad = $profile->violations($form->text);
                    $before = [$form->key_exact, $form->key_toneless, $form->key_bare, $form->sort_key];
                    $form->refreshKeys();
                    $stale = $before !== [$form->key_exact, $form->key_toneless, $form->key_bare, $form->sort_key];

                    if ($bad !== [] && ++$problems <= $limit) {
                        $this->line("  <comment>inventory</comment> {$form->lexeme->uid} \"{$form->text}\": ".implode(' ', $bad));
                    }

                    if ($stale && ++$problems <= $limit) {
                        $this->line("  <comment>stale key</comment> {$form->lexeme->uid} \"{$form->text}\" (run lexicon:reindex)");
                    }
                }
            });

        foreach ((clone $entries)->whereNull('lemma_form_id')->limit($limit)->pluck('uid') as $uid) {
            $problems++;
            $this->line("  <comment>no lemma</comment> {$uid}");
        }

        foreach ((clone $entries)->where('status', EntryStatus::Published->value)->doesntHave('senses')->limit($limit)->pluck('uid') as $uid) {
            $problems++;
            $this->line("  <comment>published without a sense</comment> {$uid}");
        }

        if ($problems > 0) {
            $this->components->error("{$problems} problem(s) found.");

            return self::FAILURE;
        }

        $this->components->info('No problems found.');

        return self::SUCCESS;
    }
}
