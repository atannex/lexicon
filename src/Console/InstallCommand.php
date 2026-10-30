<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Console;

use Atannex\Lexicon\Examples\LebialemLanguages;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Queries\LanguageRegistry;

final class InstallCommand extends TenantAwareCommand
{
    protected $signature = 'lexicon:install
        {--config : Publish config/lexicon.php}
        {--lebialem : Register the Nweh, Mundani, M\'mouck and M\'Mock identities (names only) in the shared scope}';

    protected $description = 'Set up the lexicon: publish configuration and optionally register starter language identities';

    protected function perform(): int
    {
        if ($this->option('config')) {
            $this->call('vendor:publish', ['--tag' => 'lexicon-config']);
        }

        if ($this->option('lebialem')) {
            $registry = $this->laravel->make(LanguageRegistry::class);

            foreach (LebialemLanguages::definitions() as $definition) {
                if (Language::query()->withoutGlobalScopes()->where('code', $definition->code)->exists()) {
                    $this->line("  <comment>skip</comment> {$definition->code} (already registered)");

                    continue;
                }

                $registry->register($definition);
                $this->line("  <info>added</info> {$definition->code} ({$definition->name})");
            }
        }

        $this->components->info('Run `php artisan migrate` if you have not, then `php artisan lexicon:doctor`.');

        return self::SUCCESS;
    }
}
