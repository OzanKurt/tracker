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
        'anonymize_ip' => env('TRACKER_ANONYMIZE_IP', true),
        'respect_dnt' => env('TRACKER_RESPECT_DNT', false),
        'retention_days' => (int) env('TRACKER_RETENTION_DAYS', 90), // 0 = forever
        'drop_bots' => env('TRACKER_DROP_BOTS', true),
    ],

    'cookie' => [
        'name' => 'tracker_visitor',
        'lifetime_days' => 365,
        'secure' => true,
        'http_only' => true,
        'same_site' => 'lax',
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
        'enabled' => true,
        'path' => 'tracker',
        'middleware' => ['web'],
    ],
];
