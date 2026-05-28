<?php

declare(strict_types=1);

return [
    'enabled' => env('TRACKER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | Which database connection the tracker models and migrations use.
    | Leave null to use the default app connection.
    |
    | Set to something like `mysql_tracker` to isolate tracker tables from the
    | rest of the application — handy for separate backup / retention policies,
    | or pointing analytics at a read replica / dedicated analytics database.
    | The connection name must exist in `config/database.php`.
    |
    */

    'connection' => env('TRACKER_DB_CONNECTION'),

    'dispatcher' => env('TRACKER_DISPATCHER', 'queue'), // queue | sync | defer

    'queue' => [
        'connection' => env('TRACKER_QUEUE_CONNECTION'),
        'name' => env('TRACKER_QUEUE_NAME', 'default'),
    ],

    'geoip' => [
        'driver' => env('TRACKER_GEOIP_DRIVER', 'null'), // maxmind | ipinfo | ipapi | null
        'maxmind' => [
            'database' => storage_path('app/geoip/GeoLite2-City.mmdb'),
        ],
        'ipinfo' => [
            'token' => env('IPINFO_TOKEN'),
        ],
        'ipapi' => [
            'key' => env('IPAPI_KEY'),
        ],
        'cache_ttl_days' => 30,
    ],

    'privacy' => [
        'anonymize_ip' => env('TRACKER_ANONYMIZE_IP', false),
        'respect_dnt' => env('TRACKER_RESPECT_DNT', false),
        'retention_days' => (int) env('TRACKER_RETENTION_DAYS', 90), // 0 = forever
        'drop_bots' => env('TRACKER_DROP_BOTS', true),

        /*
        | Param keys to redact from query_params and route_params before they
        | hit the database. Glob patterns supported (e.g. '*token*'). Off by
        | default — set this if your routes expose secrets in the URL.
        */
        'scrub_param_keys' => [],
    ],

    'cookie' => [
        'name' => env('TRACKER_COOKIE_NAME', 'tracker_visitor'),
        'lifetime_days' => (int) env('TRACKER_COOKIE_LIFETIME_DAYS', 365),
        'secure' => (bool) env('TRACKER_COOKIE_SECURE', true),
        'http_only' => (bool) env('TRACKER_COOKIE_HTTP_ONLY', true),
        'same_site' => env('TRACKER_COOKIE_SAME_SITE', 'lax'),
    ],

    'routes' => [
        'ignore' => [
            'tracker',
            'tracker/*',
            'telescope',
            'telescope/*',
            'horizon',
            'horizon/*',
            '_debugbar',
            '_debugbar/*',
            'livewire',
            'livewire/*',
        ],
    ],

    'dashboard' => [
        'enabled' => (bool) env('TRACKER_DASHBOARD_ENABLED', true),
        'path' => env('TRACKER_DASHBOARD_PATH', 'tracker'),
        'middleware' => ['web'],

        /*
        | Authorization gate name. Define `Gate::define('viewTracker', ...)` in
        | your AppServiceProvider. Override via env if you need a different
        | gate name (e.g. share one across multiple admin dashboards).
        */
        'gate' => env('TRACKER_DASHBOARD_GATE', 'viewTracker'),

        /*
        | App environments where the dashboard is reachable WITHOUT a defined
        | gate. Defaults to local + testing so the package is usable out of
        | the box during development. Set to [] to force every environment
        | to register the gate.
        */
        'allow_without_gate_envs' => ['local', 'testing'],
    ],
];
