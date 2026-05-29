# Changelog

All notable changes to `ozankurt/tracker` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security
- `IpApiProvider` switches to `https://` so on-path attackers can no longer
  rewrite country/city responses. Free-tier `ip-api.com` users will need to
  upgrade to the paid plan or switch to another provider.
- All three GeoIP providers (`IpApi`, `IpInfo`, `MaxMind`) reject invalid IP
  strings via `FILTER_VALIDATE_IP` before any I/O, defending against
  URL-path injection if a downstream app trusts a forged
  `X-Forwarded-For`.
- `GeoIpCache` keys entries with `hash_hmac` seeded by `APP_KEY`, so a
  stolen `tracker_geoip_cache` table can't be rainbow-tabled back to raw
  IPs.
- Dashboard authorization is now configurable via
  `tracker.dashboard.gate` (default `viewTracker`) and
  `tracker.dashboard.allow_without_gate_envs` (default
  `['local', 'testing']`). Setting the env list to `[]` forces every
  environment to register the gate — production never falls open.
- `PageViewsController` path filter escapes `LIKE` wildcards (`%`, `_`,
  `\`) with an explicit `ESCAPE '\'` clause so a crafted filter can't
  trigger a table-wide scan.
- `Tracker::optOut()` applies the same `Secure` / `HttpOnly` / `SameSite`
  flags as the visitor cookie — an HTTP downgrade can no longer strip
  the opt-out signal.

### Added
- `PrivacyFilter::anonymize(string $ip)` masks the last octet of IPv4 and
  the last 80 bits of IPv6 when `tracker.privacy.anonymize_ip` is on.
  `Enricher` applies it to `client_ip` before persisting; GeoIP lookups
  still see the real IP so country/city remain accurate.
- `PrivacyFilter::scrub(array $params)` redacts query / route params whose
  keys match any glob in `tracker.privacy.scrub_param_keys` (default
  `[]`). Use it to keep reset tokens, OAuth state, or signed URLs out
  of the DB.
- `tracker.cookie.{name,lifetime_days,secure,http_only,same_site}` are
  now env-bindable (`TRACKER_COOKIE_NAME`, `TRACKER_COOKIE_LIFETIME_DAYS`,
  `TRACKER_COOKIE_SECURE`, `TRACKER_COOKIE_HTTP_ONLY`,
  `TRACKER_COOKIE_SAME_SITE`).
- `tracker.dashboard.{enabled,path,gate,allow_without_gate_envs}` are
  now env-bindable.

### Changed
- **BC for direct callers:** `Enricher::__construct` now requires a third
  `PrivacyFilter` argument. The package's own service container resolves
  this automatically; only code that instantiates `Enricher` directly
  (typically tests) needs to update.
- `tracker.privacy.anonymize_ip` defaults to `false`. The previous default
  of `true` advertised behaviour that wasn't actually implemented — the
  feature now works, but ships off so existing installs keep recording
  full IPs. Flip `TRACKER_ANONYMIZE_IP=true` to opt in.
- `PrivacyFilter::isIgnoredRoute` automatically includes the configured
  `dashboard.path` (and its `/*` glob), so a custom dashboard path no
  longer self-tracks.

### Fixed
- IP anonymization is now actually applied. Previously the flag had no
  effect — the raw IP was stored regardless.
- `tracker_sessions.uuid` and `tracker_sessions.visitor_uuid` migrations
  use `VARCHAR(36)` instead of `CHAR(36)` so Postgres doesn't right-pad
  shorter values to the fixed width. Real UUIDs are always 36 chars,
  so existing data is unaffected.
- Bumped `ozankurt/agent` to `^1.0.5`. v1.0.5 fixes the `__call`
  signature so consumers resolving the transitive
  `mobiledetect/mobiledetectlib` to 4.11.0 (which added `: bool` to
  the parent's `__call`) no longer hit a covariance fatal at class
  load. The interim `mobiledetectlib` pin in tracker has been
  removed.

### Security (deps)
- `composer update --with-all-dependencies` ran clean —
  `composer audit` now reports no advisories. Cleared 11 advisories
  affecting 7 packages, including:
  - `symfony/http-foundation` CVE-2026-48736 (SSRF bypass via IPv6
    transition forms) → 7.4.13
  - `symfony/routing` CVE-2026-48784 (dot-segment URL normalization)
    and CVE-2026-45065 (unanchored regex host injection) → 7.4.13
  - `symfony/http-kernel` CVE-2026-45075 (HEAD bypasses
    `methods: ['GET']` on `#[IsGranted]` / `#[IsSignatureValid]` /
    `#[IsCsrfTokenValid]`) → 7.4.13
  - `symfony/mime` CVE-2026-45067 (CRLF injection in `Address`),
    CVE-2026-45070 (mime parameter header injection) → 7.4.13
  - `symfony/mailer` CVE-2026-45068 (sendmail argument injection
    via dash-prefixed address) → 7.4.12
  - `symfony/polyfill-intl-idn` CVE-2026-46644 (xn-- ASCII-only
    Punycode equivalence) → 1.38.1
  - `symfony/yaml` advisory → 7.4.13

## [1.0.3] - 2026-04-11

### Added
- New `connection` config key (env: `TRACKER_DB_CONNECTION`). Set it to point
  tracker tables at a dedicated connection — e.g. `mysql_tracker` in
  `config/database.php` — to keep analytics data on a separate database for
  backup, retention, or read-replica purposes. Leave `null` to use the default
  connection (existing behaviour, no migration needed).
- `OzanKurt\Tracker\Models\BaseModel` — all four tracker models
  (`Session`, `PageView`, `Event`, `GeoIpCache`) now extend it and honour the
  configured connection at runtime.

### Changed
- Package migrations wrap `Schema::create` / `Schema::dropIfExists` in
  `Schema::connection(config('tracker.connection'))->...` so publishing +
  migrating against a non-default connection just works.
- `TrackerStats` derives SQL dialect from the `Session` model's own connection
  instead of the global `DB::getDriverName()`, so `sessionsOverTime` and
  `pageViewsOverTime` return correct bucket expressions when tracker tables
  live on a different driver than the default app connection.

## [1.0.2] - 2026-04-11

### Fixed
- Default `routes.ignore` now excludes the bare `tracker`, `telescope`,
  `horizon`, `_debugbar`, and `livewire` paths in addition to their
  `*/` glob variants. Previously the dashboard overview page at `/tracker`
  self-tracked because `Str::is('tracker/*', 'tracker')` returns `false`.

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
