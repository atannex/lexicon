<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Editorial;

use Atannex\Lexicon\Events\LexemeCreated;
use Atannex\Lexicon\Events\LexemeDeleted;
use Atannex\Lexicon\Events\LexemeStatusChanged;
use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Extensions\ModuleExtension;
use Atannex\Lexicon\LexiconServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** Submissions, reviews and revision history. Enable with lexicon.modules.editorial. */
final class EditorialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EditorialWorkflow::class);
        $this->app->singleton(RevisionHistory::class);
        $this->app->singleton(RevisionRecorder::class);

        $this->app->instance('lexicon.extension.editorial', new ModuleExtension('editorial', ['editorial', 'editorial.submissions', 'editorial.revisions']));
        $this->app->tag('lexicon.extension.editorial', LexiconServiceProvider::TAG_EXTENSIONS);
    }

    public function boot(): void
    {
        if (config('lexicon.database.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations/editorial');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../../../database/migrations/editorial' => database_path('migrations')], 'lexicon-migrations');
        }

        Event::listen(LexemeCreated::class, [RevisionRecorder::class, 'created']);
        Event::listen(LexemeUpdated::class, [RevisionRecorder::class, 'updated']);
        Event::listen(LexemeStatusChanged::class, [RevisionRecorder::class, 'statusChanged']);
        Event::listen(LexemeDeleted::class, [RevisionRecorder::class, 'deleted']);
    }
}
