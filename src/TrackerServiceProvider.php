<?php

namespace Kurt\Tracker;

use Kurt\Support\GeoIp\GeoIp;
use Kurt\Support\PhpSession;
use Illuminate\Support\ServiceProvider;
use Kurt\Tracker\Data\Repositories\Agent;
use Kurt\Tracker\Data\Repositories\Connection;
use Kurt\Tracker\Data\Repositories\Cookie;
use Kurt\Tracker\Data\Repositories\Device;
use Kurt\Tracker\Data\Repositories\Domain;
use Kurt\Tracker\Data\Repositories\Error;
use Kurt\Tracker\Data\Repositories\Event;
use Kurt\Tracker\Data\Repositories\EventLog;
use Kurt\Tracker\Data\Repositories\GeoIp as GeoIpRepository;
use Kurt\Tracker\Data\Repositories\Language;
use Kurt\Tracker\Data\Repositories\Log;
use Kurt\Tracker\Data\Repositories\Path;
use Kurt\Tracker\Data\Repositories\Query;
use Kurt\Tracker\Data\Repositories\QueryArgument;
use Kurt\Tracker\Data\Repositories\Referer;
use Kurt\Tracker\Data\Repositories\Route;
use Kurt\Tracker\Data\Repositories\RoutePath;
use Kurt\Tracker\Data\Repositories\RoutePathParameter;
use Kurt\Tracker\Data\Repositories\Session;
use Kurt\Tracker\Data\Repositories\SqlQuery;
use Kurt\Tracker\Data\Repositories\SqlQueryBinding;
use Kurt\Tracker\Data\Repositories\SqlQueryBindingParameter;
use Kurt\Tracker\Data\Repositories\SqlQueryLog;
use Kurt\Tracker\Data\Repositories\SystemClass;
use Kurt\Tracker\Data\RepositoryManager;
use Kurt\Tracker\Eventing\EventStorage;
use Kurt\Tracker\Repositories\Message as MessageRepository;
use Kurt\Tracker\Support\CrawlerDetector;
use Kurt\Tracker\Support\Exceptions\Handler as TrackerExceptionHandler;
use Kurt\Tracker\Support\LanguageDetect;
use Kurt\Tracker\Support\MobileDetect;
use Kurt\Tracker\Support\UserAgentParser;
use Kurt\Tracker\Tracker;
use Kurt\Tracker\Artisan\Tables as TablesCommand;
use Kurt\Tracker\Artisan\UpdateGeoIp;

class TrackerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tracker.php', 'tracker');

        if (! config('tracker.enabled')) {
            return;
        }
        //     $this->registerTracker();

        //     $this->registerTablesCommand();

        //     $this->registerUpdateGeoIpCommand();

        //     $this->registerExecutionCallback();

        //     $this->registerUserCheckCallback();

        //     $this->registerSqlQueryLogWatcher();

        //     $this->registerGlobalEventLogger();

        //     $this->registerDatatables();

        //     $this->registerMessageRepository();

        //     $this->registerGlobalViewComposers();
        // }
    }

    public function boot(): void
    {
        if (! config('tracker.enabled')) {
            return;
        }

        // $this->loadRoutes();

        // $this->registerErrorHandler();

        if (config('tracker.enabled')) {
            $this->bootTracker();
        }

        // $this->loadTranslations();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides(): array
    {
        return ['tracker'];
    }

    /**
     * Takes all the components of Tracker and glues them
     * together to create Tracker.
     *
     * @return void
     */
    protected function registerTracker(): void
    {
        $this->app->singleton('tracker', function ($app) {
            $app['tracker.loaded'] = true;

            return new Tracker();
        });
    }

    protected function registerTablesCommand()
    {
        $this->app->singleton('tracker.tables', function ($app) {
            return new TablesCommand();
        });

        $this->commands('tracker.tables');
    }

    protected function registerExecutionCallback()
    {
        $me = $this;

        $mathingEvents = [
            'router.matched',
            'Illuminate\Routing\Events\RouteMatched',
        ];

        $this->app['events']->listen($mathingEvents, function () use ($me) {
            $me->getTracker()->routerMatched($me->getConfig('log_routes'));
        });
    }

    protected function registerErrorHandler()
    {
        if ($this->getConfig('log_exceptions')) {
            $illuminateHandler = 'Illuminate\Contracts\Debug\ExceptionHandler';

            $handler = new TrackerExceptionHandler(
                $this->getTracker(),
                $this->app[$illuminateHandler]
            );

            // Replace original Illuminate Exception Handler by Tracker's
            $this->app[$illuminateHandler] = $handler;
        }
    }

    /**
     * @param string $modelName
     */
    protected function instantiateModel($modelName)
    {
        $model = $this->getConfig($modelName);

        if (!$model) {
            $message = "Tracker: Model not found for '$modelName'.";

            $this->app['log']->error($message);

            throw new \Exception($message);
        }

        $model = new $model();

        $model->setConfig($this->app['tracker.config']);

        if ($connection = $this->getConfig('connection')) {
            $model->setConnection($connection);
        }

        return $model;
    }

    protected function registerSqlQueryLogWatcher()
    {
        $me = $this;

        if (!class_exists('Illuminate\Database\Events\QueryExecuted')) {
            $this->app['events']->listen('illuminate.query', function (
                $query,
                $bindings,
                $time,
                $name
            ) use ($me) {
                $me->logSqlQuery($query, $bindings, $time, $name);
            });
        } else {
            $this->app['events']->listen('Illuminate\Database\Events\QueryExecuted', function ($query) use ($me) {
                $me->logSqlQuery($query);
            });
        }
    }

    /**
     * @param $query
     * @param $bindings
     * @param $time
     * @param $name
     * @param $me
     */
    public function logSqlQuery($query, $bindings = null, $time = null, $connectionName = null)
    {
        if ($this->getTracker()->isEnabled()) {
            if ($query instanceof \Illuminate\Database\Events\QueryExecuted) {
                $bindings = $query->bindings;
                $time = $query->time;
                $connectionName = $query->connectionName;
                $query = $query->sql;
            }

            $this->getTracker()->logSqlQuery($query, $bindings, $time, $connectionName);
        }
    }

    protected function registerGlobalEventLogger()
    {
        $me = $this;

        $this->app->singleton('tracker.events', function ($app) {
            return new EventStorage();
        });

        $this->app['events']->listen('*', function ($object = null) use ($me) {
            if ($me->app['tracker.events']->isOff() || !$me->isFullyBooted()) {
                return;
            }

            // To avoid infinite recursion, event tracking while logging events
            // must be turned off
            $me->app['tracker.events']->turnOff();

            // Log events even before application is ready
            // $me->app['tracker.events']->logEvent(
            //    $me->app['events']->firing(),
            //    $object
            // );
            // TODO: we have to investigate a way of doing this

            // Can only send events to database after application is ready
            if (isset($me->app['tracker.loaded'])) {
                $me->getTracker()->logEvents();
            }

            // Turn the event tracking to on again
            $me->app['tracker.events']->turnOn();
        });
    }

    protected function loadRoutes()
    {
        if (!$this->getConfig('stats_panel_enabled')) {
            return false;
        }

        $prefix = $this->getConfig('stats_base_uri');

        $namespace = $this->getConfig('stats_controllers_namespace');

        $filters = [];

        if ($before = $this->getConfig('stats_routes_before_filter')) {
            $filters['before'] = $before;
        }

        if ($after = $this->getConfig('stats_routes_after_filter')) {
            $filters['after'] = $after;
        }

        if ($middleware = $this->getConfig('stats_routes_middleware')) {
            $filters['middleware'] = $middleware;
        }

        $router = $this->app->make('router');

        $router->group(['namespace' => $namespace], function () use ($prefix, $router, $filters) {
            $router->group($filters, function () use ($prefix, $router) {
                $router->group(['prefix' => $prefix], function ($router) {
                    $router->get('/', ['as' => 'tracker.stats.index', 'uses' => 'Stats@index']);

                    $router->get('log/{uuid}', ['as' => 'tracker.stats.log', 'uses' => 'Stats@log']);

                    $router->get('api/pageviews', ['as' => 'tracker.stats.api.pageviews', 'uses' => 'Stats@apiPageviews']);

                    $router->get('api/pageviewsbycountry', ['as' => 'tracker.stats.api.pageviewsbycountry', 'uses' => 'Stats@apiPageviewsByCountry']);

                    $router->get('api/log/{uuid}', ['as' => 'tracker.stats.api.log', 'uses' => 'Stats@apiLog']);

                    $router->get('api/errors', ['as' => 'tracker.stats.api.errors', 'uses' => 'Stats@apiErrors']);

                    $router->get('api/events', ['as' => 'tracker.stats.api.events', 'uses' => 'Stats@apiEvents']);

                    $router->get('api/users', ['as' => 'tracker.stats.api.users', 'uses' => 'Stats@apiUsers']);

                    $router->get('api/visits', ['as' => 'tracker.stats.api.visits', 'uses' => 'Stats@apiVisits']);
                });
            });
        });
    }

    protected function registerDatatables()
    {
        $this->registerServiceProvider('Bllim\Datatables\DatatablesServiceProvider');

        $this->registerServiceAlias('Datatable', 'Bllim\Datatables\Facade\Datatables');
    }

    /**
     * Get the current package directory.
     *
     * @return string
     */
    public function getPackageDir()
    {
        return __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..';
    }

    /**
     * Register global view composers.
     */
    protected function registerGlobalViewComposers()
    {
        $me = $this;

        $this->app->make('view')->composer('kurt/tracker::*', function ($view) use ($me) {
            $view->with('stats_layout', $me->getConfig('stats_layout'));

            $template_path = url('/').$me->getConfig('stats_template_path');

            $view->with('stats_template_path', $template_path);
        });
    }

    protected function registerUpdateGeoIpCommand()
    {
        $this->app->singleton('tracker.updategeoip', function ($app) {
            return new UpdateGeoIp();
        });

        $this->commands('tracker.updategeoip');
    }

    protected function registerUserCheckCallback()
    {
        $me = $this;

        $this->app['events']->listen('router.before', function ($object = null) use ($me) {

            // get auth bindings to check
            $bindings = $me->getConfig('authentication_ioc_binding');

            // check if all bindings are resolved
            $checked_bindings = array_map(function ($abstract) use ($me) {
                return $me->app->resolved($abstract);
            }, $bindings);

            $all_bindings_resolved =
                (!in_array(false, $checked_bindings, true)) ?: false;

            if ($me->tracker &&
                !$me->userChecked &&
                $me->getConfig('log_users') &&
                $all_bindings_resolved
            ) {
                $me->userChecked = $me->getTracker()->checkCurrentUser();
            }
        });
    }

    /**
     * @return Tracker
     */
    public function getTracker()
    {
        if (!$this->tracker) {
            $this->tracker = $this->app['tracker'];
        }

        return $this->tracker;
    }

    public function getRootDirectory()
    {
        return __DIR__.'/../..';
    }

    protected function getAppUrl()
    {
        return $this->app['request']->url();
    }

    public function loadTranslations()
    {
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'tracker');
    }

    /**
     * Register the message repository.
     */
    protected function registerMessageRepository()
    {
        $this->app->singleton('tracker.messages', function () {
            return new MessageRepository();
        });
    }
}
