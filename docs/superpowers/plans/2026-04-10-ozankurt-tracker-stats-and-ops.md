# ozankurt/tracker — Plan C-1: Stats & Ops

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the non-UI operational layer on top of the Plan B tracking engine — `TrackerStats` read-model service, `Authorize` gate middleware, a scheduled retention purge command, README polish, and a GitHub Actions CI workflow. End state: the package has everything needed for production use except a dashboard UI. Plan C-2 builds the Blade dashboard on top of this.

**Architecture:** `TrackerStats` exposes dashboard-ready aggregates (`topPages`, `topCountries`, `uniqueVisitors`, `sessionsOverTime`, etc.) with caching. `Authorize` checks `Gate::allows('viewTracker')` and is permissive in `local`. A new `TrackerPurgeCommand` artisan command deletes sessions older than `tracker.privacy.retention_days`. GitHub Actions runs the full Pest + PHPStan + Pint suite on every push.

**Tech Stack:** Laravel 12, Pest 3, Larastan 3, Pint. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-04-10-ozankurt-tracker-design.md` (sections: Stats, Dashboard auth gate, Retention, Testing & CI)

**Prerequisite:** Plan B complete on `main`. Work on `feat/stats-and-ops`.

---

## File Structure

Files created in this plan:

```
src/
├── Stats/
│   └── TrackerStats.php                       # read-model aggregates
├── Http/
│   └── Middleware/
│       └── Authorize.php                      # Gate::allows('viewTracker')
└── Console/
    └── Commands/
        └── PruneTrackerData.php               # artisan tracker:prune

tests/
├── Unit/
│   ├── Stats/
│   │   └── TrackerStatsTest.php
│   └── Http/
│       └── Middleware/
│           └── AuthorizeTest.php
└── Feature/
    └── PruneTrackerDataCommandTest.php

.github/
└── workflows/
    └── ci.yml                                 # Pest + PHPStan + Pint matrix

README.md                                      # significant rewrite
```

Files modified:

```
src/TrackerServiceProvider.php                # register command, bind stats, register default gate
```

---

## Task 1: TrackerStats service

**Files:**
- Create: `src/Stats/TrackerStats.php`
- Create: `tests/Unit/Stats/TrackerStatsTest.php`

Read-model wrapper over Eloquent/query builder, exposing cacheable aggregates for the eventual dashboard.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Stats/TrackerStatsTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Stats\TrackerStats;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

function seedSession(array $overrides = []): Session
{
    return Session::create(array_merge([
        'uuid'             => 'sess-' . uniqid(),
        'visitor_uuid'     => 'vis-' . uniqid(),
        'client_ip'        => '203.0.113.' . random_int(1, 254),
        'user_agent'       => 'UA',
        'device_kind'      => 'desktop',
        'device_platform'  => 'macOS',
        'browser'          => 'Chrome',
        'browser_version'  => '120',
        'language'         => 'en',
        'language_range'   => 'en-US',
        'country_code'     => 'TR',
        'country_name'     => 'Türkiye',
        'started_at'       => now(),
        'last_activity_at' => now(),
    ], $overrides));
}

function seedPageView(Session $session, string $path, ?string $routeName = null): PageView
{
    return PageView::create([
        'session_id'   => $session->id,
        'method'       => 'GET',
        'path'         => $path,
        'route_name'   => $routeName,
        'route_action' => null,
        'route_params' => [],
        'query_params' => [],
        'status_code'  => 200,
        'duration_ms'  => 10,
        'created_at'   => now(),
    ]);
}

it('counts unique visitors within a window', function () {
    $sessionA = seedSession(['visitor_uuid' => 'v1']);
    $sessionB = seedSession(['visitor_uuid' => 'v2']);
    // Same visitor, different session — should not double-count
    seedSession(['visitor_uuid' => 'v1', 'uuid' => 'sess-v1-again']);

    $stats = new TrackerStats();
    $count = $stats->uniqueVisitors(Carbon::now()->subHour());

    expect($count)->toBe(2);
});

it('returns top pages by view count', function () {
    $session = seedSession();
    seedPageView($session, '/home', 'home');
    seedPageView($session, '/home', 'home');
    seedPageView($session, '/about', 'about');

    $top = new TrackerStats()->topPages(Carbon::now()->subHour(), 10);

    expect($top)->toHaveCount(2);
    $first = $top->first();
    expect($first->path)->toBe('/home')
        ->and((int) $first->views)->toBe(2);
});

it('returns top countries by session count', function () {
    seedSession(['country_code' => 'TR', 'country_name' => 'Türkiye']);
    seedSession(['country_code' => 'TR', 'country_name' => 'Türkiye']);
    seedSession(['country_code' => 'US', 'country_name' => 'United States']);

    $top = new TrackerStats()->topCountries(Carbon::now()->subHour(), 10);

    expect($top)->toHaveCount(2);
    expect($top->first()->country_code)->toBe('TR')
        ->and((int) $top->first()->sessions)->toBe(2);
});

it('returns top browsers by session count', function () {
    seedSession(['browser' => 'Chrome']);
    seedSession(['browser' => 'Chrome']);
    seedSession(['browser' => 'Firefox']);

    $top = new TrackerStats()->topBrowsers(Carbon::now()->subHour(), 10);

    expect($top)->toHaveCount(2);
    expect($top->first()->browser)->toBe('Chrome')
        ->and((int) $top->first()->sessions)->toBe(2);
});

it('buckets sessions over time by hour', function () {
    Carbon::setTestNow('2026-04-10 14:30:00');
    seedSession(['started_at' => '2026-04-10 13:10:00', 'last_activity_at' => '2026-04-10 13:10:00']);
    seedSession(['started_at' => '2026-04-10 13:45:00', 'last_activity_at' => '2026-04-10 13:45:00']);
    seedSession(['started_at' => '2026-04-10 14:05:00', 'last_activity_at' => '2026-04-10 14:05:00']);

    $buckets = new TrackerStats()->sessionsOverTime(Carbon::parse('2026-04-10 12:00:00'), 'hour');

    expect($buckets)->not->toBeEmpty();
    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run test → FAIL**

Run: `./vendor/bin/pest tests/Unit/Stats/TrackerStatsTest.php`

- [ ] **Step 3: Write the service**

Create `src/Stats/TrackerStats.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Stats;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

class TrackerStats
{
    public function uniqueVisitors(Carbon $since): int
    {
        return (int) Session::where('started_at', '>=', $since)
            ->distinct('visitor_uuid')
            ->count('visitor_uuid');
    }

    /**
     * @return Collection<int, object{path: string, views: int}>
     */
    public function topPages(Carbon $since, int $limit = 10): Collection
    {
        /** @var Collection<int, object{path: string, views: int}> $result */
        $result = PageView::where('created_at', '>=', $since)
            ->select('path', DB::raw('COUNT(*) as views'))
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, object{country_code: ?string, country_name: ?string, sessions: int}>
     */
    public function topCountries(Carbon $since, int $limit = 10): Collection
    {
        /** @var Collection<int, object{country_code: ?string, country_name: ?string, sessions: int}> $result */
        $result = Session::where('started_at', '>=', $since)
            ->whereNotNull('country_code')
            ->select('country_code', 'country_name', DB::raw('COUNT(*) as sessions'))
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, object{browser: string, sessions: int}>
     */
    public function topBrowsers(Carbon $since, int $limit = 10): Collection
    {
        /** @var Collection<int, object{browser: string, sessions: int}> $result */
        $result = Session::where('started_at', '>=', $since)
            ->select('browser', DB::raw('COUNT(*) as sessions'))
            ->groupBy('browser')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, object{device_kind: string, sessions: int}>
     */
    public function topDevices(Carbon $since, int $limit = 10): Collection
    {
        /** @var Collection<int, object{device_kind: string, sessions: int}> $result */
        $result = Session::where('started_at', '>=', $since)
            ->select('device_kind', DB::raw('COUNT(*) as sessions'))
            ->groupBy('device_kind')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, object{referer_domain: ?string, referer_medium: ?string, sessions: int}>
     */
    public function topReferers(Carbon $since, int $limit = 10): Collection
    {
        /** @var Collection<int, object{referer_domain: ?string, referer_medium: ?string, sessions: int}> $result */
        $result = Session::where('started_at', '>=', $since)
            ->whereNotNull('referer_domain')
            ->select('referer_domain', 'referer_medium', DB::raw('COUNT(*) as sessions'))
            ->groupBy('referer_domain', 'referer_medium')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * Bucket sessions over time. Returns rows of [bucket, count] keyed by the time bucket.
     *
     * @param  'hour'|'day'  $interval
     * @return Collection<int, object{bucket: string, sessions: int}>
     */
    public function sessionsOverTime(Carbon $since, string $interval = 'hour'): Collection
    {
        $format = $interval === 'day' ? '%Y-%m-%d' : '%Y-%m-%d %H:00:00';

        $driver = Session::getConnection()->getDriverName();

        $expression = match ($driver) {
            'mysql'  => "DATE_FORMAT(started_at, '{$format}')",
            'pgsql'  => "TO_CHAR(started_at, " . ($interval === 'day' ? "'YYYY-MM-DD'" : "'YYYY-MM-DD HH24:00:00'") . ")",
            default  => $interval === 'day'
                ? "strftime('%Y-%m-%d', started_at)"
                : "strftime('%Y-%m-%d %H:00:00', started_at)",
        };

        /** @var Collection<int, object{bucket: string, sessions: int}> $result */
        $result = Session::where('started_at', '>=', $since)
            ->select(DB::raw($expression . ' as bucket'), DB::raw('COUNT(*) as sessions'))
            ->groupBy(DB::raw($expression))
            ->orderBy(DB::raw($expression))
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, object{bucket: string, views: int}>
     */
    public function pageViewsOverTime(Carbon $since, string $interval = 'hour'): Collection
    {
        $format = $interval === 'day' ? '%Y-%m-%d' : '%Y-%m-%d %H:00:00';

        $driver = PageView::getConnection()->getDriverName();

        $expression = match ($driver) {
            'mysql'  => "DATE_FORMAT(created_at, '{$format}')",
            'pgsql'  => "TO_CHAR(created_at, " . ($interval === 'day' ? "'YYYY-MM-DD'" : "'YYYY-MM-DD HH24:00:00'") . ")",
            default  => $interval === 'day'
                ? "strftime('%Y-%m-%d', created_at)"
                : "strftime('%Y-%m-%d %H:00:00', created_at)",
        };

        /** @var Collection<int, object{bucket: string, views: int}> $result */
        $result = PageView::where('created_at', '>=', $since)
            ->select(DB::raw($expression . ' as bucket'), DB::raw('COUNT(*) as views'))
            ->groupBy(DB::raw($expression))
            ->orderBy(DB::raw($expression))
            ->get();

        return $result;
    }
}
```

**Note on SQL portability**: the `DATE_FORMAT` / `TO_CHAR` / `strftime` switch is necessary because MySQL, Postgres, and SQLite each use different bucketing functions. The tests run on SQLite, so the `default` branch is exercised there.

- [ ] **Step 4: Run test → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Stats/TrackerStats.php tests/Unit/Stats/TrackerStatsTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add TrackerStats read-model service"
```

---

## Task 2: Register TrackerStats in the service provider

**Files:**
- Modify: `src/TrackerServiceProvider.php`

- [ ] **Step 1: Add the binding**

Edit `register()` to add after the `DispatcherManager` binding:

```php
use OzanKurt\Tracker\Stats\TrackerStats;

// (inside register())
// Stats
$this->app->singleton(TrackerStats::class);
```

- [ ] **Step 2: Run pest → still 59+ passing**

- [ ] **Step 3: Commit**

```bash
git add src/TrackerServiceProvider.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): bind TrackerStats in the service provider"
```

---

## Task 3: Authorize middleware (Gate-based dashboard auth)

**Files:**
- Create: `src/Http/Middleware/Authorize.php`
- Create: `tests/Unit/Http/Middleware/AuthorizeTest.php`

Checks `Gate::allows('viewTracker', $request->user())`. Permissive in `local`/`testing` by default — the package registers a default gate that returns `true` only in those environments. In production the host app must define the `viewTracker` gate explicitly.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Http/Middleware/AuthorizeTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Http\Middleware\Authorize;

it('allows the request when the viewTracker gate returns true', function () {
    Gate::define('viewTracker', fn ($user = null) => true);

    $middleware = new Authorize();
    $response = $middleware->handle(Request::create('/tracker'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('aborts with 403 when the viewTracker gate returns false', function () {
    Gate::define('viewTracker', fn ($user = null) => false);

    $middleware = new Authorize();

    expect(fn () => $middleware->handle(Request::create('/tracker'), fn () => new Response('ok')))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('allows in local environment when no gate is defined', function () {
    app()['env'] = 'local';

    $middleware = new Authorize();
    $response = $middleware->handle(Request::create('/tracker'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('denies in production environment when no gate is defined', function () {
    app()['env'] = 'production';

    $middleware = new Authorize();

    expect(fn () => $middleware->handle(Request::create('/tracker'), fn () => new Response('ok')))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write the middleware**

Create `src/Http/Middleware/Authorize.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAuthorized($request)) {
            return $next($request);
        }

        abort(403, 'Unauthorized to access tracker dashboard.');
    }

    private function isAuthorized(Request $request): bool
    {
        if (Gate::has('viewTracker')) {
            return Gate::allows('viewTracker', $request->user());
        }

        // No gate defined → permissive only in non-production environments
        return in_array(app()->environment(), ['local', 'testing'], true);
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Http/Middleware/Authorize.php tests/Unit/Http/Middleware/AuthorizeTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add Authorize middleware for dashboard gate"
```

---

## Task 4: Retention purge command

**Files:**
- Create: `src/Console/Commands/PruneTrackerData.php`
- Create: `tests/Feature/PruneTrackerDataCommandTest.php`

Deletes sessions older than `tracker.privacy.retention_days`. Page views and events cascade via FK. Does nothing if `retention_days = 0`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PruneTrackerDataCommandTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    config()->set('tracker.privacy.retention_days', 30);
});

function seedOldSession(): Session
{
    return Session::create([
        'uuid'             => 'old-' . uniqid(),
        'visitor_uuid'     => 'v-' . uniqid(),
        'client_ip'        => '203.0.113.1',
        'user_agent'       => 'UA',
        'device_kind'      => 'desktop',
        'device_platform'  => 'macOS',
        'browser'          => 'Chrome',
        'browser_version'  => '120',
        'language'         => 'en',
        'language_range'   => 'en-US',
        'started_at'       => now()->subDays(45),
        'last_activity_at' => now()->subDays(45),
    ]);
}

function seedFreshSession(): Session
{
    return Session::create([
        'uuid'             => 'fresh-' . uniqid(),
        'visitor_uuid'     => 'v-' . uniqid(),
        'client_ip'        => '203.0.113.2',
        'user_agent'       => 'UA',
        'device_kind'      => 'desktop',
        'device_platform'  => 'macOS',
        'browser'          => 'Chrome',
        'browser_version'  => '120',
        'language'         => 'en',
        'language_range'   => 'en-US',
        'started_at'       => now()->subDays(3),
        'last_activity_at' => now()->subDays(3),
    ]);
}

it('prunes sessions older than retention_days', function () {
    $old   = seedOldSession();
    $fresh = seedFreshSession();

    PageView::create([
        'session_id' => $old->id, 'method' => 'GET', 'path' => '/',
        'route_name' => null, 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'status_code' => 200, 'duration_ms' => 10,
        'created_at' => now()->subDays(45),
    ]);
    Event::create([
        'session_id' => $old->id, 'name' => 'old.event',
        'payload' => [], 'created_at' => now()->subDays(45),
    ]);

    Artisan::call('tracker:prune');

    expect(Session::count())->toBe(1)
        ->and(Session::first()->id)->toBe($fresh->id)
        ->and(PageView::count())->toBe(0) // cascaded
        ->and(Event::count())->toBe(0);   // cascaded
});

it('does nothing when retention_days is 0', function () {
    config()->set('tracker.privacy.retention_days', 0);
    seedOldSession();

    Artisan::call('tracker:prune');

    expect(Session::count())->toBe(1);
});

it('reports the number of deleted sessions', function () {
    seedOldSession();
    seedOldSession();
    seedFreshSession();

    Artisan::call('tracker:prune');
    $output = Artisan::output();

    expect($output)->toContain('2');
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write the command**

Create `src/Console/Commands/PruneTrackerData.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Models\Session;

class PruneTrackerData extends Command
{
    /** @var string */
    protected $signature = 'tracker:prune';

    /** @var string */
    protected $description = 'Delete tracker sessions older than tracker.privacy.retention_days';

    public function handle(): int
    {
        $days = (int) config('tracker.privacy.retention_days', 0);

        if ($days <= 0) {
            $this->info('Retention disabled (tracker.privacy.retention_days <= 0). Nothing to prune.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays($days);

        $deleted = Session::where('started_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} tracker sessions older than {$days} days.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Register the command in the service provider**

Edit `src/TrackerServiceProvider.php` — add to the `boot()` method inside the `runningInConsole()` block:

```php
use OzanKurt\Tracker\Console\Commands\PruneTrackerData;

// (inside boot(), inside the runningInConsole() guard)
$this->commands([
    PruneTrackerData::class,
]);
```

- [ ] **Step 5: Run → PASS**

- [ ] **Step 6: Commit**

```bash
git add src/Console/Commands/PruneTrackerData.php \
        src/TrackerServiceProvider.php \
        tests/Feature/PruneTrackerDataCommandTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add tracker:prune retention command"
```

---

## Task 5: README rewrite

**Files:**
- Modify: `README.md`

Replace the minimal stub with a proper user-facing README.

- [ ] **Step 1: Write the README**

Replace `README.md` with:

```markdown
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

// In bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [TrackRequests::class]);
})
```

## Quick usage

```php
use OzanKurt\Tracker\Facades\Tracker;

// Read the current visitor's session
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

## Dispatchers

- **`queue`** (default): middleware pushes a `ProcessTrackerPayload` job. Processing happens in a queue worker. ~1ms overhead per request.
- **`sync`**: processing runs inline during the request. Useful in tests.
- **`defer`**: processing runs in the middleware's `terminate()` after the response is sent. No queue worker needed; fine for PHP-FPM under moderate load, great for Laravel Octane.

## Geo-IP providers

All providers are optional. Install the one you want and set `TRACKER_GEOIP_DRIVER`:

- **`null`** (default): no geo-IP lookup
- **`ipapi`**: uses `ip-api.com` (free tier, no key)
- **`ipinfo`**: uses `ipinfo.io` (set `IPINFO_TOKEN`)
- **`maxmind`**: uses MaxMind GeoLite2 (offline). Requires `composer require geoip2/geoip2` and a GeoLite2-City.mmdb file at `storage/app/geoip/GeoLite2-City.mmdb`

## Retention purge

Sessions older than `privacy.retention_days` can be pruned via:

```bash
php artisan tracker:prune
```

Schedule it in your `routes/console.php`:

```php
use Illuminate\Console\Scheduling\Schedule;

Schedule::command('tracker:prune')->daily();
```

## Dashboard

An admin dashboard is shipped in a companion release (Plan C-2). For now, the read API is fully available via the `Tracker` facade and `OzanKurt\Tracker\Stats\TrackerStats`:

```php
use OzanKurt\Tracker\Stats\TrackerStats;

$stats = app(TrackerStats::class);
$stats->uniqueVisitors(now()->subDay());
$stats->topPages(now()->subDay(), limit: 10);
$stats->topCountries(now()->subDay());
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
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "docs: rewrite README with installation and usage"
```

---

## Task 6: GitHub Actions CI workflow

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Write the workflow**

Create `.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    name: Pest (PHP ${{ matrix.php }}, ${{ matrix.db }})
    runs-on: ubuntu-latest

    strategy:
      fail-fast: false
      matrix:
        php: ['8.3', '8.4']
        db: [sqlite, mysql, pgsql]

    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: tracker_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h localhost"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

      postgres:
        image: postgres:16
        env:
          POSTGRES_PASSWORD: postgres
          POSTGRES_DB: tracker_test
        ports:
          - 5432:5432
        options: >-
          --health-cmd="pg_isready -U postgres"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: pdo, pdo_mysql, pdo_pgsql, pdo_sqlite, mbstring, curl, intl
          coverage: none

      - name: Cache composer dependencies
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ matrix.php }}-${{ hashFiles('composer.json') }}
          restore-keys: composer-${{ matrix.php }}-

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Run Pest
        env:
          DB_CONNECTION: ${{ matrix.db }}
          DB_HOST: 127.0.0.1
          DB_DATABASE: tracker_test
          DB_USERNAME: ${{ matrix.db == 'mysql' && 'root' || 'postgres' }}
          DB_PASSWORD: ${{ matrix.db == 'mysql' && 'root' || 'postgres' }}
        run: ./vendor/bin/pest

  static-analysis:
    name: Static analysis
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, curl, intl
          coverage: none
      - name: Install
        run: composer install --prefer-dist --no-interaction --no-progress
      - name: PHPStan
        run: ./vendor/bin/phpstan analyse --no-progress

  code-style:
    name: Code style
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, curl, intl
          coverage: none
      - name: Install
        run: composer install --prefer-dist --no-interaction --no-progress
      - name: Pint
        run: ./vendor/bin/pint --test
```

**Note**: The SQLite job runs with the default in-memory database from `tests/TestCase.php`. The MySQL/Postgres jobs override via env vars — you'll need a small test helper to read these. If your `TestCase.php` doesn't honor env vars, update `defineEnvironment()` to check `env('DB_CONNECTION')` and switch accordingly.

Quick helper for `tests/TestCase.php` (if needed):

```php
protected function defineEnvironment($app): void
{
    $driver = env('DB_CONNECTION', 'sqlite');

    $app['config']->set('database.default', 'testing');
    $app['config']->set('database.connections.testing', match ($driver) {
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'database' => env('DB_DATABASE', 'tracker_test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'database' => env('DB_DATABASE', 'tracker_test'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
        ],
        default => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
    });
}
```

Update the TestCase accordingly. Run the full Pest suite against SQLite locally to confirm no regression — we cannot run MySQL/Postgres locally without infrastructure, but the matrix will catch issues in CI.

- [ ] **Step 2: Verify local Pest still green**

```bash
./vendor/bin/pest
```

Expected: 59+ tests passing (original count + new Plan C-1 tests).

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/ci.yml tests/TestCase.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "ci: add GitHub Actions workflow with PHP + DB matrix"
```

---

## Task 7: Green check

- [ ] **Step 1: Full suite**

```bash
./vendor/bin/pest
./vendor/bin/phpstan analyse --no-progress
./vendor/bin/pint --test
```

Expected: all green. Test count should be 59 (Plan B) + ~13 new (TrackerStats x5 + Authorize x4 + Prune x3 + misc) ≈ 72+ tests passing.

- [ ] **Step 2: Fix anything red, commit fixups, re-run**

---

## Definition of Done

- `TrackerStats` service exists with `uniqueVisitors`, `topPages`, `topCountries`, `topBrowsers`, `topDevices`, `topReferers`, `sessionsOverTime`, `pageViewsOverTime`, all tested
- `Authorize` middleware checks `viewTracker` gate, permissive in local/testing, tested
- `tracker:prune` artisan command deletes old sessions, tested
- README rewrite with full usage docs
- GitHub Actions CI workflow with PHP 8.3/8.4 × SQLite/MySQL/Postgres matrix + phpstan + pint jobs
- Full suite green: pest, phpstan, pint
- All tasks committed on `feat/stats-and-ops`

After this, Plan C-2 (Dashboard UI) builds the Blade + Tailwind + Alpine dashboard on top of `TrackerStats`.
