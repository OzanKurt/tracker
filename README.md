# ozankurt/tracker

Modern, privacy-first Laravel visitor analytics.

[![PHP](https://img.shields.io/badge/php-%5E8.3-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-%5E12-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Successor to `pragmarx/tracker`, rewritten from scratch for Laravel 12 and PHP 8.3.

## What it tracks

- **Sessions** (per-visitor, tied to a long-lived cookie)
- **Page views** (every route hit, with route name and params)
- **Users** (when authenticated)
- **Devices, browsers, operating systems, languages**
- **Geo-IP** (country, city, coordinates — via pluggable providers)
- **Referers** (organic search, social, direct, with search terms when available)
- **Custom events** (`Tracker::logEvent('signup.completed', ['plan' => 'pro'])`)

## What it doesn't track

- SQL queries, exceptions, system classes — use Telescope / Sentry / Flare
- Anything when the user has set `DNT: 1` or the opt-out cookie
- Bots (configurable — defaults to dropping crawlers)

## Requirements

- PHP 8.3+
- Laravel 12+
- MySQL 8+, Postgres 16+, or SQLite

## Installation

```bash
composer require ozankurt/tracker
```

The package auto-registers via Laravel's service provider discovery.

Publish the config and run migrations:

```bash
php artisan vendor:publish --tag=tracker-config
php artisan vendor:publish --tag=tracker-migrations
php artisan migrate
```

Register the middleware in your route groups (or globally in `bootstrap/app.php`):

```php
use OzanKurt\Tracker\Http\Middleware\TrackRequests;

// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [TrackRequests::class]);
})
```

## Quick usage

```php
use OzanKurt\Tracker\Facades\Tracker;

// Current visitor's session
$session = Tracker::currentSession();

// Log a custom event
Tracker::logEvent('signup.completed', ['plan' => 'pro']);

// Query recent sessions
$recent = Tracker::sessions(minutes: 60);

// Online users (last 3 minutes of activity)
$online = Tracker::onlineUsers();

// Privacy helpers
Tracker::optOut();      // user clicks "don't track me"
Tracker::optIn();       // undo
Tracker::hasOptedOut(); // cookie check
```

## Configuration

See `config/tracker.php` after publishing. Highlights:

| Key | Default | Purpose |
|---|---|---|
| `enabled` | `true` | Global kill switch |
| `dispatcher` | `queue` | `queue`, `sync`, or `defer` |
| `geoip.driver` | `null` | `null`, `ipapi`, `ipinfo`, `maxmind` |
| `privacy.anonymize_ip` | `true` | Zero last octet of IPv4 |
| `privacy.respect_dnt` | `true` | Honor `DNT: 1` header |
| `privacy.drop_bots` | `true` | Skip crawler requests |
| `privacy.retention_days` | `90` | Auto-purge old sessions |
| `cookie.lifetime_days` | `365` | Visitor cookie lifetime |
| `routes.ignore` | `[tracker/*, telescope/*, ...]` | Path glob patterns to skip |

## Dispatchers

- **`queue`** (default) — middleware pushes a `ProcessTrackerPayload` job; processing happens in a queue worker. ~1ms overhead per request.
- **`sync`** — processing runs inline during the request. Useful in tests.
- **`defer`** — processing runs in the middleware's `terminate()` after the response is sent. No queue worker needed; good for Laravel Octane.

## Geo-IP providers

All providers are optional. Install the one you want and set `TRACKER_GEOIP_DRIVER`:

- **`null`** (default) — no geo lookup
- **`ipapi`** — uses `ip-api.com` (free tier, no key)
- **`ipinfo`** — uses `ipinfo.io` (set `IPINFO_TOKEN`)
- **`maxmind`** — uses MaxMind GeoLite2 (offline). Requires `composer require geoip2/geoip2` and a `GeoLite2-City.mmdb` file at `storage/app/geoip/GeoLite2-City.mmdb`

Lookups are cached in `tracker_geoip_cache` for 30 days by default.

## Retention purge

Sessions older than `privacy.retention_days` can be pruned via:

```bash
php artisan tracker:prune
```

Schedule it in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('tracker:prune')->daily();
```

## Dashboard

An admin dashboard ships in a companion release. For now, the read API is fully available via the `Tracker` facade and `OzanKurt\Tracker\Stats\TrackerStats`:

```php
use OzanKurt\Tracker\Stats\TrackerStats;

$stats = app(TrackerStats::class);

$stats->uniqueVisitors(now()->subDay());
$stats->topPages(now()->subDay(), limit: 10);
$stats->topCountries(now()->subDay());
$stats->topBrowsers(now()->subDay());
$stats->topDevices(now()->subDay());
$stats->topReferers(now()->subDay());
$stats->sessionsOverTime(now()->subDay(), interval: 'hour');
$stats->pageViewsOverTime(now()->subDay(), interval: 'hour');
```

When the dashboard lands, protect it by defining a gate in your `AuthServiceProvider`:

```php
Gate::define('viewTracker', function ($user) {
    return $user?->is_admin === true;
});
```

## Testing

```bash
composer install
./vendor/bin/pest
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
```

## License

MIT © Ozan Kurt
