<?php

use OzanKurt\Tracker\Models\Agent;
use OzanKurt\Tracker\Models\Connection;
use OzanKurt\Tracker\Models\Cookie;
use OzanKurt\Tracker\Models\Device;
use OzanKurt\Tracker\Models\Domain;
use OzanKurt\Tracker\Models\Error;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\EventLog;
use OzanKurt\Tracker\Models\GeoIp;
use OzanKurt\Tracker\Models\Language;
use OzanKurt\Tracker\Models\Log;
use OzanKurt\Tracker\Models\Path;
use OzanKurt\Tracker\Models\Query;
use OzanKurt\Tracker\Models\QueryArgument;
use OzanKurt\Tracker\Models\Referer;
use OzanKurt\Tracker\Models\RefererSearchTerm;
use OzanKurt\Tracker\Models\Route;
use OzanKurt\Tracker\Models\RoutePath;
use OzanKurt\Tracker\Models\RoutePathParameter;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Models\SqlQuery;
use OzanKurt\Tracker\Models\SqlQueryBinding;
use OzanKurt\Tracker\Models\SqlQueryBindingParameter;
use OzanKurt\Tracker\Models\SqlQueryLog;
use OzanKurt\Tracker\Models\SystemClass;
use OzanKurt\Tracker\Models\User;

return [

    /**
     * Enable it?
     */
    'enabled' => true,

    /**
     * Enable cache?
     */
    'cache_enabled' => true,

    /**
     * Deffer booting for middleware use
     */
    'use_middleware' => false,

    /**
     * Robots should be tracked?
     */
    'do_not_track_robots' => false,

    /**
     * Which environments are not trackable?
     */
    'do_not_track_environments' => [
        // defaults to none
    ],

    /**
     * Which routes names are not trackable?
     */
    'do_not_track_routes' => [
        'tracker.stats.*',
    ],

    /**
     * Which route paths are not trackable?
     */
    'do_not_track_paths' => [
        'api/**',
    ],

    /**
     * The Do Not Track Ips is used to disable Tracker for some IP addresses:
     *
     *     '127.0.0.1', '192.168.1.1'
     *
     * You can set ranges of IPs
     *     '192.168.0.1-192.168.0.100'
     *
     * And use net masks
     *     '10.0.0.0/32'
     *     '172.17.0.0/255.255.0.0'
     */
    'do_not_track_ips' => [
        '127.0.0.0/24', /// range 127.0.0.1 - 127.0.0.255
    ],

    /**
     * When an IP is not trackable, show a message in log.
     */
    'log_untrackable_sessions' => true,

    /**
     * Log every single access?
     *
     * The log table can become huge if your site is popular, but...
     *
     * Log table is also responsible for storing information on:
     *
     *    - Routes and controller actions accessed
     *    - HTTP method used (GET, POST...)
     *    - Error log
     *    - URL queries (including values)
     */
    'log_enabled' => false,

    /**
     * Log artisan commands?
     */
    'console_log_enabled' => false,

    /**
     * Log SQL queries?
     *
     * Log must be enabled for this option to work.
     */
    'log_sql_queries' => false,

    /**
     * If you prefer to store Tracker data on a different database or connection,
     * you can set it here.
     *
     * To avoid SQL queries log recursion, create a different connection for Tracker,
     * point it to the same database (or not) and forbid logging of this connection in
     * do_not_log_sql_queries_connections.
     */
    'connection' => 'tracker',

    /**
     * Forbid logging of SQL queries for some connections.
     *
     * To avoid recursion, you better ignore Tracker connection here.
     *
     * Please create a separate database connection for Tracker. It can hit
     * the same database of your application, but the connection itself
     * has to have a different name, so the package can ignore its own queries
     * and avoid recursion.
     *
     */
    'do_not_log_sql_queries_connections' => [
        'tracker',
    ],

    /**
     * GeoIp2 database path.
     *
     * To get a fresh version of this file, use the command
     *
     *      php artisan tracker:updategeoip
     *
     */

    'geoip_database_path' => __DIR__ . '/geoip', //storage_path('geoip'),

    /**
     * Also log SQL query bindings?
     *
     * Log must be enabled for this option to work.
     */
    'log_sql_queries_bindings' => false,

    /**
     * Log events?
     */
    'log_events' => false,

    /**
     * Which events do you want to log exactly?
     */
    'log_only_events' => [
        // defaults to logging all events
    ],

    /**
     * What are the names of the id columns on your system?
     *
     * 'id' is the most common, but if you have one or more different,
     * please add them here in your preference order.
     */
    'id_columns_names' => [
        'id',
    ],

    /**
     * Do not log events for the following patterns.
     * Strings accepts wildcards:
     *
     *    eloquent.*
     *
     */
    'do_not_log_events' => [
        'illuminate.log',
        'eloquent.*',
        'router.*',
        'composing: *',
        'creating: *',
    ],

    /**
     * Do you wish to log Geo IP data?
     *
     * You will need to install the geoip package
     *
     *     composer require "geoip/geoip":"~1.14"
     *
     * And remove the PHP module
     *
     *     sudo apt-get purge php5-geoip
     *
     */
    'log_geoip' => false,

    /**
     * Do you wish to log the user agent?
     */
    'log_user_agents' => false,

    /**
     * Do you wish to log your users?
     */
    'log_users' => false,

    /**
     * Do you wish to log devices?
     */
    'log_devices' => false,

    /**
     * Do you wish to log languages?
     */
    'log_languages' => false,

    /**
     * Do you wish to log HTTP referers?
     */
    'log_referers' => false,

    /**
     * Do you wish to log url paths?
     */
    'log_paths' => false,

    /**
     * Do you wish to log url queries and query arguments?
     */
    'log_queries' => false,

    /**
     * Do you wish to log routes and route parameters?
     */
    'log_routes' => false,

    /**
     * Log errors and exceptions?
     */
    'log_exceptions' => false,

    /**
     * A cookie may be created on your visitor device, so you can have information
     * on everything made using that device on your site.     *
     */
    'store_cookie_tracker' => false,

    /**
     * If you are storing cookies, you better change it to a name you of your own.
     */
    'tracker_cookie_name' => 'please_change_this_cookie_name',

    /**
     * Internal tracker session name.
     */
    'tracker_session_name' => 'tracker_session',

    /**
     * ** IMPORTANT **
     * Change the user model to your own.
     * If the model is under a different connection, be specific.
     * ...
     * class ModelName {
     *      protected $connection = 'mysql';
     * ...
     */
    'user_model' => User::class,

    'tables' => [
        'agents' => Agent::class,
        'connections' => Connection::class,
        'cookies' => Cookie::class,
        'devices' => Device::class,
        'domains' => Domain::class,
        'errors' => Error::class,
        'event_logs' => EventLog::class,
        'events' => Event::class,
        'geoips' => GeoIp::class,
        'languages' => Language::class,
        'logs' => Log::class,
        'paths' => Path::class,
        'query_arguments' => QueryArgument::class,
        'queries' => Query::class,
        'referers' => Referer::class,
        'referer_search_terms' => RefererSearchTerm::class,
        'routes' => Route::class,
        'route_paths' => RoutePath::class,
        'route_path_parameters' => RoutePathParameter::class,
        'sessions' => Session::class,
        'sql_query_bindings' => SqlQueryBinding::class,
        'sql_query_binding_parameters' => SqlQueryBindingParameter::class,
        'sql_query_logs' => SqlQueryLog::class,
        'sql_queries' => SqlQuery::class,
        'system_classs' => SystemClass::class,
    ],

    /**
     * You can use your own model for every single table Tracker has.
     */
    'models' => [
        'agent' => Agent::class,
        'connection' => Connection::class,
        'cookie' => Cookie::class,
        'device' => Device::class,
        'domain' => Domain::class,
        'error' => Error::class,
        'event_log' => EventLog::class,
        'event' => Event::class,
        'geoip' => GeoIp::class,
        'language' => Language::class,
        'log' => Log::class,
        'path' => Path::class,
        'query_argument' => QueryArgument::class,
        'query' => Query::class,
        'referer' => Referer::class,
        'referer_search_term' => RefererSearchTerm::class,
        'route' => Route::class,
        'route_path' => RoutePath::class,
        'route_path_parameter' => RoutePathParameter::class,
        'session' => Session::class,
        'sql_query_binding' => SqlQueryBinding::class,
        'sql_query_binding_parameter' => SqlQueryBindingParameter::class,
        'sql_query_log' => SqlQueryLog::class,
        'sql_query' => SqlQuery::class,
        'system_class' => SystemClass::class,
    ],

    /**
     * Enable the Stats Panel?
     */
    'stats_panel_enabled' => false,

    /**
     * Stats Panel routes before filter
     *
     */
    'stats_routes_before_filter' => '',

    /**
     * Stats Panel routes after filter
     *
     */
    'stats_routes_after_filter' => '',

    /**
     * Stats Panel routes middleware
     *
     */
    'stats_routes_middleware' => 'web',

    /**
     * Stats Panel template path
     */
    'stats_template_path' => '/templates/sb-admin-2',

    /**
     * Stats Panel base uri.
     *
     * If your site url is http://wwww.mysite.com, then your stats page will be:
     *
     *    http://wwww.mysite.com/stats
     *
     */
    'stats_base_uri' => 'stats',

    /**
     * Stats Panel layout view
     */
    'stats_layout' => 'kurt/tracker::layout',

    /**
     * Stats Panel controllers namespace
     */
    'stats_controller' => Kurt\Tracker\Controllers\StatsController::class,

    /**
     * Set a default user agent
     */
    'default_user_agent' => '',
];
