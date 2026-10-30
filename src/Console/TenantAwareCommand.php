<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Console;

use Atannex\Lexicon\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/** Every lexicon command takes --tenant; without it the resolver decides. */
abstract class TenantAwareCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Run as this tenant (default: the resolver\'s current tenant)');
    }

    public function handle(TenantContext $tenants): int
    {
        $tenant = $this->option('tenant');

        if (! is_string($tenant)) {
            return $this->perform();
        }

        return $tenants->run($tenant, fn (): int => $this->perform());
    }

    abstract protected function perform(): int;

    protected function text(string $argument): string
    {
        $value = $this->argument($argument);

        return is_scalar($value) ? (string) $value : '';
    }

    protected function textOption(string $option): ?string
    {
        $value = $this->option($option);

        return is_scalar($value) ? (string) $value : null;
    }
}
