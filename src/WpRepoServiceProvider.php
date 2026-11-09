<?php

namespace Apachish\WpRepo;

use Apachish\WpRepo\Console\CheckSitesCommand;
use Apachish\WpRepo\Console\DeployCommand;
use Apachish\WpRepo\Console\PruneCommand;
use Apachish\WpRepo\Console\SyncCommand;
use Apachish\WpRepo\Contracts\EntitlementChecker;
use Apachish\WpRepo\Livewire\Connections;
use Apachish\WpRepo\Livewire\Logs;
use Apachish\WpRepo\Livewire\PackageDetail;
use Apachish\WpRepo\Livewire\Packages;
use Apachish\WpRepo\Livewire\Sites;
use Apachish\WpRepo\Services\GitManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class WpRepoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/wp-repo.php', 'wp-repo');
        $this->mergeConfigFrom(__DIR__.'/../config/seeder.php', 'package-seeders');

        $this->app->singleton(GitManager::class);
        $this->app->bind(EntitlementChecker::class, fn ($app) => $app->make(config('wp-repo.entitlement')));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'wp-repo');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'wp-repo');

        if (config('wp-repo.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        }

        $this->registerRoutes();
        $this->registerGate();
        $this->registerLivewire();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncCommand::class, DeployCommand::class, PruneCommand::class, CheckSitesCommand::class]);

            $this->publishes([__DIR__.'/../config/wp-repo.php' => config_path('wp-repo.php')], 'wp-repo-config');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'wp-repo-migrations');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/wp-repo')], 'wp-repo-views');
            $this->publishes([__DIR__.'/../resources/lang' => lang_path('vendor/wp-repo')], 'wp-repo-lang');
            $this->publishes([__DIR__.'/../stubs/apachish-wp-updater.php' => base_path('stubs/apachish-wp-updater.php')], 'wp-repo-mu-plugin');

            if (config('wp-repo.schedule', true)) {
                $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                    $schedule->command('wp-repo:prune')->weekly();
                    $schedule->command('wp-repo:check-sites')->daily();
                });
            }
        }
    }

    protected function registerRoutes(): void
    {
        // The API routes are not in the "web"/"api" group, so route-model binding ({version}) needs
        // SubstituteBindings explicitly; without it the controller receives an EMPTY model.
        $apiMiddleware = array_values(array_unique(array_merge(
            (array) config('wp-repo.api.middleware', []),
            [SubstituteBindings::class]
        )));

        Route::middleware($apiMiddleware)
            ->prefix(config('wp-repo.api.prefix', 'api/wp-updates'))
            ->group(__DIR__.'/../routes/api.php');

        if (config('wp-repo.panel.enabled', true)) {
            Route::middleware(config('wp-repo.panel.middleware', ['web', 'auth']))
                ->prefix(config('wp-repo.panel.prefix', 'wp-repo'))
                ->as('wp-repo.panel.')
                ->group(__DIR__.'/../routes/web.php');
        }
    }

    protected function registerGate(): void
    {
        // Default gate: users with a truthy `is_admin` attribute. Override in your AuthServiceProvider.
        $this->app->booted(function () {
            if (! Gate::has('manage-wp-repo')) {
                Gate::define('manage-wp-repo', fn ($user) => (bool) ($user->is_admin ?? false));
            }
        });
    }

    protected function registerLivewire(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component('wp-repo.packages', Packages::class);
        Livewire::component('wp-repo.package-detail', PackageDetail::class);
        Livewire::component('wp-repo.connections', Connections::class);
        Livewire::component('wp-repo.sites', Sites::class);
        Livewire::component('wp-repo.logs', Logs::class);
    }
}
