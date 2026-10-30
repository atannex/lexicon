<?php

declare(strict_types=1);

namespace Atannex\Lexicon;

use Atannex\Lexicon\Authorization\LexemePolicy;
use Atannex\Lexicon\Console\DoctorCommand;
use Atannex\Lexicon\Console\InstallCommand;
use Atannex\Lexicon\Console\LanguagesCommand;
use Atannex\Lexicon\Console\ReindexCommand;
use Atannex\Lexicon\Console\StatsCommand;
use Atannex\Lexicon\Console\ValidateCommand;
use Atannex\Lexicon\Contracts\Extension;
use Atannex\Lexicon\Contracts\OrthographyProfile;
use Atannex\Lexicon\Contracts\SearchEngine;
use Atannex\Lexicon\Contracts\TenantResolver;
use Atannex\Lexicon\Extensions\ExtensionRegistry;
use Atannex\Lexicon\Extensions\ModuleExtension;
use Atannex\Lexicon\Models\Definition;
use Atannex\Lexicon\Models\Example;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Sense;
use Atannex\Lexicon\Models\TranslationEquivalent;
use Atannex\Lexicon\Modules\Api\ApiServiceProvider;
use Atannex\Lexicon\Modules\Editorial\EditorialServiceProvider;
use Atannex\Lexicon\Modules\Exchange\ExchangeServiceProvider;
use Atannex\Lexicon\Orthography\OrthographyRegistry;
use Atannex\Lexicon\Queries\LanguageRegistry;
use Atannex\Lexicon\Search\DatabaseSearchEngine;
use Atannex\Lexicon\Tenancy\NullTenantResolver;
use Atannex\Lexicon\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class LexiconServiceProvider extends ServiceProvider
{
    /** Tag Extension instances or classes with this to list them in the registry. */
    public const string TAG_EXTENSIONS = 'lexicon.extensions';

    /** Polymorphic aliases, so stored types survive class renames. */
    public const array MORPH_MAP = [
        'lexicon.lexeme' => Lexeme::class,
        'lexicon.form' => Form::class,
        'lexicon.sense' => Sense::class,
        'lexicon.definition' => Definition::class,
        'lexicon.example' => Example::class,
        'lexicon.equivalent' => TranslationEquivalent::class,
    ];

    private const array MODULES = [
        'editorial' => EditorialServiceProvider::class,
        'exchange' => ExchangeServiceProvider::class,
        'api' => ApiServiceProvider::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lexicon.php', 'lexicon');

        $this->app->singleton(TenantResolver::class, function (Container $app): TenantResolver {
            $class = config('lexicon.tenant_resolver');

            return is_string($class) && $class !== '' ? $this->instance($app, $class, TenantResolver::class) : new NullTenantResolver;
        });

        $this->app->singleton(TenantContext::class);
        $this->app->scoped(LanguageRegistry::class);

        $this->app->singleton(OrthographyRegistry::class, function (Container $app): OrthographyRegistry {
            $rules = config('lexicon.orthography.rules', []);
            $registry = new OrthographyRegistry($app, is_array($rules) ? $rules : []);

            foreach ((array) config('lexicon.orthography.profiles', []) as $key => $class) {
                if (! is_string($class) || ! is_subclass_of($class, OrthographyProfile::class)) {
                    throw new InvalidArgumentException("lexicon.orthography.profiles.{$key} must name an OrthographyProfile class.");
                }

                $registry->register((string) $key, $class);
            }

            return $registry;
        });

        $this->app->singleton(SearchEngine::class, function (Container $app): SearchEngine {
            $class = config('lexicon.search.engine', DatabaseSearchEngine::class);

            return $this->instance($app, is_string($class) ? $class : DatabaseSearchEngine::class, SearchEngine::class);
        });

        $this->app->singleton(ExtensionRegistry::class, function (Container $app): ExtensionRegistry {
            $registry = new ExtensionRegistry;
            $registry->register(new ModuleExtension('core', ['languages', 'varieties', 'orthography', 'lexemes', 'provenance', 'search.database']));

            foreach ($app->tagged(self::TAG_EXTENSIONS) as $extension) {
                if (! $extension instanceof Extension) {
                    throw new InvalidArgumentException('Services tagged '.self::TAG_EXTENSIONS.' must implement '.Extension::class.'.');
                }

                $registry->register($extension);
            }

            return $registry;
        });

        $this->app->singleton(Lexicon::class);

        foreach (self::MODULES as $module => $provider) {
            if (config("lexicon.modules.{$module}")) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        Relation::morphMap(self::MORPH_MAP);

        if (Gate::getPolicyFor(Lexeme::class) === null) {
            Gate::policy(Lexeme::class, LexemePolicy::class);
        }

        if (config('lexicon.database.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/lexicon.php' => config_path('lexicon.php')], 'lexicon-config');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'lexicon-migrations');

            $this->commands([
                InstallCommand::class,
                DoctorCommand::class,
                LanguagesCommand::class,
                StatsCommand::class,
                ValidateCommand::class,
                ReindexCommand::class,
            ]);
        }
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T
     */
    private function instance(Container $app, string $class, string $contract): object
    {
        $instance = $app->make($class);

        if (! $instance instanceof $contract) {
            throw new InvalidArgumentException("[{$class}] must implement {$contract}.");
        }

        return $instance;
    }
}
