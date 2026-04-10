<?php

declare(strict_types=1);

namespace OzanKurt\Tracker;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OzanKurt\Tracker\Console\Commands\PruneTrackerData;
use OzanKurt\Tracker\Dispatchers\DeferredDispatcher;
use OzanKurt\Tracker\Dispatchers\DispatcherManager;
use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\Http\Middleware\Authorize;
use OzanKurt\Tracker\Repositories\EventRepository;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;
use OzanKurt\Tracker\Repositories\PageViewRepository;
use OzanKurt\Tracker\Repositories\RepositoryManager;
use OzanKurt\Tracker\Repositories\SessionRepository;
use OzanKurt\Tracker\Stats\TrackerStats;
use OzanKurt\Tracker\Support\BotFilter;
use OzanKurt\Tracker\Support\Enricher;
use OzanKurt\Tracker\Support\Pipeline;
use OzanKurt\Tracker\Support\PrivacyFilter;
use OzanKurt\Tracker\Support\RefererParser;
use OzanKurt\Tracker\Support\VisitorCookie;

class TrackerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tracker.php', 'tracker');

        // Repositories
        $this->app->singleton(SessionRepository::class);
        $this->app->singleton(PageViewRepository::class);
        $this->app->singleton(EventRepository::class);
        $this->app->singleton(GeoIpCacheRepository::class);
        $this->app->singleton(RepositoryManager::class);

        // Support
        $this->app->singleton(PrivacyFilter::class);
        $this->app->singleton(BotFilter::class);
        $this->app->singleton(VisitorCookie::class);
        $this->app->singleton(RefererParser::class);
        $this->app->singleton(Enricher::class);
        $this->app->singleton(Pipeline::class);

        // Geo IP
        $this->app->singleton(GeoIpManager::class);

        // Dispatchers
        $this->app->singleton(DispatcherManager::class);
        $this->app->singleton(DeferredDispatcher::class); // must be shared across handle/terminate

        // Stats
        $this->app->singleton(TrackerStats::class);

        // Main service — the real Tracker class still has no constructor args at this point
        // (it'll be rewritten in the next task to depend on DispatcherManager + VisitorCookie)
        $this->app->singleton(Tracker::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/tracker.php' => config_path('tracker.php'),
            ], 'tracker-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'tracker-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/tracker'),
            ], 'tracker-views');

            $this->commands([
                PruneTrackerData::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tracker');

        if ((bool) config('tracker.dashboard.enabled', true)) {
            Route::group([
                'prefix' => (string) config('tracker.dashboard.path', 'tracker'),
                'middleware' => array_merge(
                    (array) config('tracker.dashboard.middleware', ['web']),
                    [Authorize::class],
                ),
                'as' => 'tracker.',
            ], function (): void {
                $this->loadRoutesFrom(__DIR__.'/../routes/dashboard.php');
            });
        }
    }
}
