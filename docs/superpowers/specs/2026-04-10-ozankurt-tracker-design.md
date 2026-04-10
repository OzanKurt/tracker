# ozankurt/tracker — Design Spec

**Date:** 2026-04-10
**Status:** Draft — pending user review
**Replaces:** `pragmarx/tracker` (legacy, Laravel 5–8, PHP 7.0)

## Goal

A modern, privacy-first Laravel visitor analytics package for Laravel 12+ / PHP 8.3+, inspired by the feature set of `pragmarx/tracker` but rewritten from scratch under the `OzanKurt\Tracker\` namespace. Not API-compatible with the old package.

## Scope

### In scope (v1)
- **Core visitor analytics**: sessions, page views, users, devices, browsers, OS, languages, geo-IP, referers
- **Custom events**: `Tracker::logEvent($name, $payload)` tied to the current session
- **Bundled admin dashboard**: server-rendered Blade + Alpine.js + Tailwind (no build step for consumers)
- **Privacy-first defaults**: IP anonymization, DNT respect, opt-out cookie, retention purge, bot dropping
- **Pluggable drivers**: dispatch (queue/sync/defer) and geo-IP providers

### Out of scope (v1)
- Error/exception tracking — use Sentry / Flare
- SQL query logging, DB connection tracking, system class resolution — use Telescope / Debugbar
- Data import from legacy `pragmarx/tracker` schema — can ship later as a separate importer package
- Public JSON API or SPA dashboard — Blade only for v1

## Target Stack

- **PHP**: 8.3+
- **Laravel**: 12.x (and later)
- **Database**: MySQL 8+, Postgres 16+, SQLite (for tests)
- **Namespace**: `OzanKurt\Tracker\`
- **Composer name**: `ozankurt/tracker`

## Dependencies

### Required
- `php ^8.3`
- `laravel/framework ^12.0`
- `ozankurt/agent` — user-agent parsing (device, browser, OS, bot detection, language)

### Suggested (on-demand, for pluggable drivers)
- `geoip2/geoip2` — for MaxMind GeoLite2 geo-IP driver
- Any HTTP-based provider is called via Laravel's `Http` client — no extra composer deps

### Dev
- `pestphp/pest ^3`
- `orchestra/testbench ^10`
- `larastan/larastan ^3`
- `laravel/pint`

## Architecture

### Shape: Service + Repository + Driver

- **`Tracker` service** — single facade target, small surface, delegates to repositories and dispatcher
- **Repositories** — one per Eloquent model, isolate persistence from business logic, mockable in tests
- **Drivers** — two pluggable concerns, swapped via config:
  - *Dispatcher*: `queue` (default), `sync` (tests), `defer` (terminable middleware, no queue worker needed)
  - *Geo-IP provider*: `maxmind`, `ipinfo`, `ipapi`, `null` (default)
- **Enricher** — transforms a raw request payload into a persistable structure by running it through UA parsing (`ozankurt/agent`), geo-IP lookup, and referer parsing
- **Support classes** — `BotFilter`, `PrivacyFilter`, `VisitorCookie`
- **Stats service** — `TrackerStats`, read-model used by the dashboard, cacheable

### Package Layout

```
ozankurt/tracker/
├── composer.json
├── config/tracker.php
├── database/migrations/
├── resources/
│   ├── views/                    # Blade dashboard
│   ├── css/tracker.css           # Tailwind source
│   ├── js/tracker.js             # Alpine glue
│   └── dist/                     # pre-built CSS/JS, committed
├── routes/web.php                # /tracker/* dashboard routes
├── src/
│   ├── Tracker.php
│   ├── TrackerServiceProvider.php
│   ├── Facades/Tracker.php
│   ├── Http/
│   │   ├── Middleware/
│   │   │   ├── TrackRequests.php
│   │   │   └── Authorize.php
│   │   └── Controllers/Dashboard/
│   ├── Jobs/ProcessTrackerPayload.php
│   ├── Dispatchers/
│   │   ├── DispatcherInterface.php
│   │   ├── QueueDispatcher.php
│   │   ├── SyncDispatcher.php
│   │   └── DeferredDispatcher.php
│   ├── GeoIp/
│   │   ├── GeoIpProviderInterface.php
│   │   ├── MaxMindProvider.php
│   │   ├── IpInfoProvider.php
│   │   ├── IpApiProvider.php
│   │   └── NullProvider.php
│   ├── Models/
│   │   ├── Session.php
│   │   ├── PageView.php
│   │   ├── Event.php
│   │   └── GeoIpCache.php
│   ├── Repositories/
│   │   ├── RepositoryManager.php
│   │   ├── SessionRepository.php
│   │   ├── PageViewRepository.php
│   │   ├── EventRepository.php
│   │   └── GeoIpCacheRepository.php
│   ├── Support/
│   │   ├── Pipeline.php            # driver-agnostic processing pipeline
│   │   ├── Enricher.php
│   │   ├── BotFilter.php
│   │   ├── PrivacyFilter.php
│   │   ├── VisitorCookie.php
│   │   └── RefererParser.php
│   └── Stats/
│       └── TrackerStats.php
└── tests/
```

## Database Schema

Lean normalized — four tables, no join-heavy dedup tables. JSON columns for rarely-queried structured data.

### `tracker_sessions`

One row per visit session.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `uuid` | char(36) unique | session id |
| `visitor_uuid` | char(36) index | long-lived cookie id |
| `user_id` | bigint nullable index | FK to app users table |
| `client_ip` | varchar(45) index | anonymized per privacy config |
| `user_agent` | text | raw UA string |
| `device_kind` | varchar(32) | desktop/mobile/tablet/bot |
| `device_model` | varchar(64) nullable | |
| `device_platform` | varchar(32) | iOS / Windows / macOS / ... |
| `device_platform_ver` | varchar(32) nullable | |
| `browser` | varchar(64) | |
| `browser_version` | varchar(32) | |
| `language` | varchar(10) | |
| `language_range` | varchar(64) | full Accept-Language |
| `is_robot` | boolean default false | |
| `country_code` | char(2) nullable index | |
| `country_name` | varchar(128) nullable | |
| `city` | varchar(128) nullable | |
| `latitude` | decimal(9,6) nullable | |
| `longitude` | decimal(9,6) nullable | |
| `referer_url` | text nullable | |
| `referer_domain` | varchar(255) nullable index | |
| `referer_medium` | varchar(32) nullable | search/social/email/direct |
| `referer_source` | varchar(64) nullable | google/twitter/... |
| `referer_search_term` | varchar(255) nullable | |
| `started_at` | timestamp index | |
| `last_activity_at` | timestamp index | |
| `ended_at` | timestamp nullable | |
| `page_views_count` | int default 0 | denormalized counter |
| `events_count` | int default 0 | denormalized counter |
| `created_at`, `updated_at` | timestamps | |

### `tracker_page_views`

One row per route hit.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `session_id` | bigint FK → `tracker_sessions` index | |
| `method` | varchar(8) | |
| `path` | varchar(2048) | not indexed directly — exceeds MySQL 767-byte key limit; use `route_name` for aggregation |
| `route_name` | varchar(128) nullable index | |
| `route_action` | varchar(255) nullable | |
| `route_params` | json nullable | |
| `query_params` | json nullable | |
| `status_code` | smallint nullable | |
| `duration_ms` | int nullable | |
| `created_at` | timestamp index | |

### `tracker_events`

Custom application events.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `session_id` | bigint FK → `tracker_sessions` index | |
| `name` | varchar(128) index | |
| `payload` | json nullable | |
| `created_at` | timestamp index | |

### `tracker_geoip_cache`

IP → geo lookup cache. Keyed on a hash of the (already-anonymized) IP.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `ip_hash` | char(64) unique | sha256 of anonymized IP |
| `country_code` | char(2) nullable | |
| `country_name` | varchar(128) nullable | |
| `city` | varchar(128) nullable | |
| `latitude` | decimal(9,6) nullable | |
| `longitude` | decimal(9,6) nullable | |
| `provider` | varchar(32) | which driver produced the record |
| `cached_until` | timestamp index | |
| `created_at` | timestamp | |

## Data Flow

### Processing pipeline (driver-agnostic)

The heart of the tracker is a deterministic pipeline that takes a raw payload and persists it. Every dispatcher eventually runs this same pipeline — they differ only in *when* and *where* it runs.

```
Pipeline::process($payload)
  │
  ├─ BotFilter::isBot($ua)              — drop silently if bot and drop_bots=true
  ├─ Enricher::enrich($payload)
  │    ├─ ozankurt/agent   → device, browser, OS, platform, language
  │    ├─ GeoIpProvider    → country, city, lat/lng (via cache)
  │    └─ RefererParser    → medium, source, search term
  │
  ├─ SessionRepository::upsertByUuid()
  ├─ PageViewRepository::create()
  ├─ (queued events) EventRepository::create()
  └─ Bump session.last_activity_at, page_views_count, events_count
```

### Request lifecycle

```
Request
  │
  ▼
TrackRequests middleware (handle)
  │
  ├─ PrivacyFilter::shouldTrack()       — DNT? opt-out cookie? tracker disabled?
  │                                       ignored route? abort if any true.
  ├─ VisitorCookie::ensure()            — set visitor_uuid cookie if missing
  └─ Build raw payload:
       { ip, ua, method, url, route, route_params, query_params,
         visitor_uuid, session_id, user_id, t0 }
  │
  ▼
Dispatcher::dispatch($payload)          — config: queue | sync | defer
  │
  ├─ QueueDispatcher  → push ProcessTrackerPayload job (sub-ms), return
  ├─ SyncDispatcher   → run Pipeline::process() inline, return
  └─ DeferredDispatcher → stash payload on the middleware instance
  │
  ▼
Response sent to client
  │
  ▼
(queue driver)    Worker picks up ProcessTrackerPayload → Pipeline::process()
(defer driver)    TrackRequests::terminate() → Pipeline::process() on stashed payload
(sync driver)     (already ran before response)
```

`Tracker::logEvent()` follows the same path: it builds an event payload, runs through the active dispatcher, and persists via `EventRepository` inside the pipeline.

## Configuration (`config/tracker.php`)

```php
return [
    'enabled' => env('TRACKER_ENABLED', true),

    'dispatcher' => env('TRACKER_DISPATCHER', 'queue'), // queue | sync | defer

    'queue' => [
        'connection' => env('TRACKER_QUEUE_CONNECTION'),
        'name'       => env('TRACKER_QUEUE_NAME', 'default'),
    ],

    'geoip' => [
        'driver' => env('TRACKER_GEOIP_DRIVER', 'null'), // maxmind | ipinfo | ipapi | null
        'maxmind' => [ 'database' => storage_path('app/geoip/GeoLite2-City.mmdb') ],
        'ipinfo'  => [ 'token' => env('IPINFO_TOKEN') ],
        'ipapi'   => [ 'key'   => env('IPAPI_KEY') ],
        'cache_ttl_days' => 30,
    ],

    'privacy' => [
        'anonymize_ip'   => true,
        'respect_dnt'    => true,
        'retention_days' => 90,   // 0 = forever
        'drop_bots'      => true,
    ],

    'cookie' => [
        'name'          => 'tracker_visitor',
        'lifetime_days' => 365,
        'secure'        => true,
        'http_only'     => true,
        'same_site'     => 'lax',
    ],

    'routes' => [
        'ignore' => [
            'tracker/*',
            'telescope/*',
            'horizon/*',
            '_debugbar/*',
            'livewire/*',
        ],
    ],

    'dashboard' => [
        'enabled'    => true,
        'path'       => 'tracker',
        'middleware' => ['web', \OzanKurt\Tracker\Http\Middleware\Authorize::class],
    ],
];
```

## Facade API

Full parity with the old package minus the dropped buckets.

```php
// Current request
Tracker::currentSession(): ?Session
Tracker::currentVisitor(): ?Visitor
Tracker::sessionId(): ?string
Tracker::visitorId(): ?string

// Collections / queries
Tracker::sessions(int $minutes = 1440): Collection
Tracker::onlineUsers(int $minutes = 3): Collection
Tracker::users(int $minutes = 1440): Collection
Tracker::pageViews(int $minutes = 1440): Collection
Tracker::events(int $minutes = 1440, ?string $name = null): Collection

// Writes
Tracker::logEvent(string $name, array $payload = []): void

// Runtime control
Tracker::enable(): void
Tracker::disable(): void
Tracker::isEnabled(): bool

// Privacy
Tracker::optOut(): void
Tracker::optIn(): void
Tracker::hasOptedOut(): bool
```

Eloquent relationships on `Session`:

```php
$session->user        // BelongsTo app user
$session->pageViews   // HasMany
$session->events      // HasMany
```

### `TrackerStats` (dashboard read model)

```php
TrackerStats::topPages(Carbon $since, int $limit = 10)
TrackerStats::topCountries(Carbon $since, int $limit = 10)
TrackerStats::topReferers(Carbon $since, int $limit = 10)
TrackerStats::topBrowsers(Carbon $since, int $limit = 10)
TrackerStats::topDevices(Carbon $since, int $limit = 10)
TrackerStats::uniqueVisitors(Carbon $since): int
TrackerStats::sessionsOverTime(Carbon $since, string $interval = 'hour')
TrackerStats::pageViewsOverTime(Carbon $since, string $interval = 'hour')
```

All stats methods are cacheable via `Cache::remember` with a short TTL (configurable, default 60s).

## Admin Dashboard

Routes mounted under `tracker.dashboard.path` (default `/tracker`). The `Authorize` middleware checks `Gate::allows('viewTracker', $request->user())`. In `local` env the gate is permissive by default; in production it denies until the user defines the gate in their `AuthServiceProvider`.

### Pages

| Route | Purpose |
|---|---|
| `GET /tracker` | Overview: unique visitors, sessions, page views over time (Chart.js), top pages/countries/browsers/devices, online users count |
| `GET /tracker/sessions` | Paginated session list with filters (date, country, device, browser, user) |
| `GET /tracker/sessions/{uuid}` | Session detail: visitor, device, geo, referer, chronological page view + event timeline |
| `GET /tracker/page-views` | Paginated page view list, filterable by path/route/status |
| `GET /tracker/events` | Paginated event list, filterable by name |
| `GET /tracker/users/{id}` | All sessions for a given app user |

### Stack

- **Blade** layouts, single `tracker::layout` parent
- **Tailwind CSS** — source in `resources/css/tracker.css`, pre-built via an internal Vite config at package-release time, compiled file committed to `resources/dist/tracker.css`
- **Alpine.js** — tiny interactions (filter dropdowns, modals). Loaded from `resources/dist/tracker.js` or CDN fallback
- **Chart.js** — graphs, same delivery model
- **Zero npm on the consumer side** — consumers run `php artisan vendor:publish --tag=tracker-assets` and get the pre-built files

## Privacy & GDPR Posture

Privacy-first, opinionated defaults — everything is configurable, but the defaults ship compliant.

- **IP anonymization**: IPv4 last octet zeroed, IPv6 last 80 bits zeroed. Enabled by default. Disable with `privacy.anonymize_ip = false`.
- **Do-Not-Track**: requests with `DNT: 1` are skipped when `privacy.respect_dnt = true`.
- **Opt-out cookie**: `Tracker::optOut()` sets a persistent cookie. When present, tracking is skipped for that browser.
- **Bot dropping**: `BotFilter::isBot()` (backed by `ozankurt/agent`) drops crawler sessions entirely when `privacy.drop_bots = true`.
- **Retention purge**: a scheduled command `tracker:purge` deletes sessions (and cascades page views + events via FK `onDelete('cascade')`) older than `privacy.retention_days`. Registered to run daily via the package's service provider when `retention_days > 0`.
- **Cookie hygiene**: visitor cookie is signed, HTTP-only, `SameSite=Lax`, `Secure` in production, opaque UUID (no PII).

## Testing & CI

- **Framework**: Pest 3 + `orchestra/testbench ^10`
- **Database**: SQLite in-memory for fast local tests; MySQL 8 + Postgres 16 in CI matrix
- **Static analysis**: Larastan level 8
- **Code style**: Laravel Pint

### Test surface

- **Unit**
  - `Enricher` — happy path + fallback when geo driver fails
  - `BotFilter` — various UA strings
  - `PrivacyFilter` — DNT, opt-out cookie, disabled state, ignored routes
  - `VisitorCookie` — create / read / rotate
  - Each `Dispatcher` (queue, sync, defer)
  - Each `GeoIpProvider`, including `NullProvider` and cache hit/miss
  - `TrackerStats` — each aggregate method against a seeded dataset
- **Feature**
  - Middleware end-to-end with `sync` dispatcher (real DB writes, assert session + page view rows)
  - Facade API (`logEvent`, `currentSession`, `optOut`/`optIn` cycle)
  - Dashboard routes: 403 without gate, 200 with gate, each page renders
  - Retention purge command
- **CI matrix** (GitHub Actions)
  - PHP 8.3, 8.4
  - Laravel 12
  - MySQL 8, Postgres 16, SQLite
  - Pint check, Larastan level 8, Pest with coverage

## Migration Path from Legacy Package

Not supported in v1. `ozankurt/tracker` is a fresh package with a fresh schema. If demand exists, ship a separate `ozankurt/tracker-legacy-importer` later that reads the old `pragmarx/tracker` schema and writes into the new one.

## Open Questions

None at time of writing. All major architectural decisions have been made.
