<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Tests;

use Atannex\Lexicon\LexiconServiceProvider;
use Atannex\Lexicon\Modules\Api\ApiServiceProvider;
use Atannex\Lexicon\Modules\Editorial\EditorialServiceProvider;
use Atannex\Lexicon\Modules\Exchange\ExchangeServiceProvider;

/**
 * Every optional module enabled, database refreshed per test. Testbench
 * registers providers before defineEnvironment() runs, so the modules are
 * listed here rather than switched on through lexicon.modules.*.
 */
abstract class ModuleTestCase extends DatabaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LexiconServiceProvider::class, EditorialServiceProvider::class, ExchangeServiceProvider::class, ApiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach (['editorial', 'exchange', 'api'] as $module) {
            $app['config']->set("lexicon.modules.{$module}", true);
        }
    }
}
