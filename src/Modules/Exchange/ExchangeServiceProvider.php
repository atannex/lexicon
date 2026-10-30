<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Exchange;

use Atannex\Lexicon\Extensions\ModuleExtension;
use Atannex\Lexicon\LexiconServiceProvider;
use Atannex\Lexicon\Modules\Exchange\Console\ExportCommand;
use Atannex\Lexicon\Modules\Exchange\Console\ImportCommand;
use Illuminate\Support\ServiceProvider;

/** Import and export. Enable with lexicon.modules.exchange. */
final class ExchangeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('lexicon.extension.exchange', new ModuleExtension('exchange', ['exchange', 'exchange.jsonl', 'exchange.json', 'exchange.csv']));
        $this->app->tag('lexicon.extension.exchange', LexiconServiceProvider::TAG_EXTENSIONS);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportCommand::class, ExportCommand::class]);
        }
    }
}
