# Changelog

All notable changes to `ozankurt/tracker` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2026-04-11

### Changed
- **BC:** `privacy.respect_dnt` now defaults to `false`. The previous default
  silently dropped every visitor whose browser sends `DNT: 1` (Firefox, Brave,
  Tor), which in practice meant the tracker recorded nothing for a large slice
  of real traffic. Set `TRACKER_RESPECT_DNT=true` to restore the old behavior.
- Privacy settings are now env-driven: `TRACKER_ANONYMIZE_IP`,
  `TRACKER_RESPECT_DNT`, `TRACKER_RETENTION_DAYS`, `TRACKER_DROP_BOTS`.
- `Enricher` now calls `Agent::version()` and `Agent::device()` directly after
  `ozankurt/agent v1.0.4` fixed the upstream `self::VER`/`getUtilities()` bugs.
  The `safeVersion` / `safeDevice` try/catch workarounds were removed.
- Minimum `ozankurt/agent` version bumped to `^1.0.4`.

## [1.0.0] - 2026-04-11

First public release. A complete rewrite from the legacy `pragmarx/tracker` package,
targeting Laravel 12 and PHP 8.3 with a privacy-first default posture.

### Added

#### Core tracking engine

- Request capture via `TrackRequests` middleware with `handle()` and `terminate()` support
- Per-visitor sessions (long-lived cookie) and per-request page views
- User-agent parsing via `ozankurt/agent` (device kind, platform, browser, version)
- Pluggable geo-IP drivers: `null` (default), `ipapi`, `ipinfo`, `maxmind`
- Per-IP geo-IP result caching in `tracker_geoip_cache`
- Referer parsing with known-host classification (search / social / referral / internal / direct)
  and search-term extraction for major engines
- Custom event logging via `Tracker::logEvent($name, $payload)`
- Authenticated-user linking on sessions (`user_id` column)

#### Dispatchers

- `sync` — inline processing (ideal for tests)
- `queue` (default) — dispatches `ProcessTrackerPayload` job; ~1ms request overhead
- `defer` — flushes in terminable middleware, no queue worker required

#### Privacy and compliance

- IP anonymization (opt-in, on by default) — last octet of IPv4, last 80 bits of IPv6
- `DNT: 1` header respected by default
- Opt-out cookie helpers (`Tracker::optOut()`, `optIn()`, `hasOptedOut()`)
- Configurable bot dropping via `ozankurt/agent` crawler detection
- Configurable retention purge via `php artisan tracker:prune`
- Opaque UUID visitor cookie (signed, HTTP-only, `SameSite=Lax`, `Secure` in production)

#### Facade API

- `Tracker::currentSession()`, `sessionId()`, `visitorId()`
- `Tracker::sessions(int $minutes)`, `onlineUsers(int $minutes)`, `users(int $minutes)`
- `Tracker::pageViews(int $minutes)`, `events(int $minutes, ?string $name)`
- `Tracker::logEvent(string $name, array $payload)`
- `Tracker::enable()`, `disable()`, `isEnabled()`

#### Stats

- `TrackerStats::uniqueVisitors(Carbon $since)`
- `TrackerStats::topPages()`, `topCountries()`, `topBrowsers()`, `topDevices()`, `topReferers()`
- `TrackerStats::sessionsOverTime()`, `pageViewsOverTime()` — MySQL / Postgres / SQLite portable

#### Admin dashboard (bundled)

- Mounted under configurable path (default `/tracker`), protected by `Authorize` middleware
- Gate-based auth via `Gate::allows('viewTracker')`, permissive in `local`/`testing`
- Overview page with stat cards, Chart.js line chart, and top-5 lists
- Paginated sessions listing with country / device / browser filters
- Session detail page with visitor / device / location cards and chronological timeline
  (page views and events interleaved)
- Paginated page views listing with path substring and route-name filters
- Paginated events listing with name filter
- Per-user sessions page at `/tracker/users/{id}`
- Tailwind + Alpine.js + Chart.js delivered via CDN — no npm build step for consumers

#### Database schema

- Lean 4-table normalized design:
  - `tracker_sessions` — one row per visit session
  - `tracker_page_views` — one row per route hit, FK cascade to sessions
  - `tracker_events` — one row per custom event, FK cascade to sessions
  - `tracker_geoip_cache` — hashed-IP lookup cache
- Eloquent models with relationships, JSON casts, and PHPDoc property annotations
- Native JSON columns for `route_params`, `query_params`, and event `payload`

#### Developer experience

- Pest 3 test suite with `orchestra/testbench ^10`, currently 83 passing tests
- Larastan level 8 static analysis, zero warnings
- Laravel Pint with `declare_strict_types`, `strict_comparison`, `strict_param` enforced
- GitHub Actions CI matrix: PHP 8.3 / 8.4 × SQLite / MySQL 8 / Postgres 16
  plus static analysis and code-style jobs
- `.gitattributes` enforcing LF line endings for cross-platform consistency

### Decisions

- No error tracking (Sentry / Flare already own that space)
- No SQL query or connection logging (Telescope / Debugbar already own that space)
- No legacy schema import from `pragmarx/tracker` (will ship as a separate package if
  there's demand)
- Assets (Tailwind / Alpine / Chart.js) delivered via CDN to eliminate the package's
  build pipeline — can revisit with a compiled-asset delivery later
