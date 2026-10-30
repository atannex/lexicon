<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Api;

use Atannex\Lexicon\Extensions\ExtensionRegistry;
use Atannex\Lexicon\Extensions\ModuleExtension;
use Atannex\Lexicon\LexiconServiceProvider;
use Atannex\Lexicon\Modules\Api\Http\Controllers\EntriesController;
use Atannex\Lexicon\Modules\Api\Http\Controllers\LanguagesController;
use Atannex\Lexicon\Modules\Api\Http\Controllers\SearchController;
use Atannex\Lexicon\Modules\Api\Http\Controllers\SubmissionsController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Versioned JSON API. Enable with lexicon.modules.api. Reads are open to
 * anonymous readers (published, public entries only); writes run behind
 * lexicon.api.write_middleware and the Lexeme policy.
 */
final class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('lexicon.extension.api', new ModuleExtension('api', ['api', 'api.v1']));
        $this->app->tag('lexicon.extension.api', LexiconServiceProvider::TAG_EXTENSIONS);
    }

    public function boot(): void
    {
        RateLimiter::for('lexicon-api', static function (Request $request): Limit {
            $id = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('lexicon.api.rate_limit', 60))->by(is_scalar($id) ? 'user:'.$id : 'ip:'.$request->ip());
        });

        $prefix = (string) config('lexicon.api.prefix', 'api/lexicon/v1');

        Route::prefix($prefix)
            ->middleware([...(array) config('lexicon.api.middleware', ['api']), 'throttle:lexicon-api'])
            ->name('lexicon.api.')
            ->group(function (): void {
                Route::get('languages', [LanguagesController::class, 'index'])->name('languages.index');
                Route::get('languages/{language}', [LanguagesController::class, 'show'])->name('languages.show');
                Route::get('languages/{language}/entries', [EntriesController::class, 'index'])->name('entries.index');
                Route::get('entries/{entry}', [EntriesController::class, 'show'])->name('entries.show');
                Route::get('search', SearchController::class)->name('search');

                Route::middleware((array) config('lexicon.api.write_middleware', ['auth']))->group(function (): void {
                    Route::post('entries', [EntriesController::class, 'store'])->name('entries.store');
                    Route::patch('entries/{entry}', [EntriesController::class, 'update'])->name('entries.update');
                    Route::delete('entries/{entry}', [EntriesController::class, 'destroy'])->name('entries.destroy');

                    if ($this->app->make(ExtensionRegistry::class)->has('editorial')) {
                        Route::post('submissions', [SubmissionsController::class, 'store'])->name('submissions.store');
                    }
                });
            });
    }
}
