# ozankurt/tracker — Plan B: Tracking Engine

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete tracking runtime on top of the Plan A foundation — repositories, support layer, pluggable geo-IP drivers, enricher, processing pipeline, dispatchers (queue/sync/defer), the TrackRequests middleware, and the real `Tracker` facade implementation. End state: registering the middleware on a host app causes visitor data to be captured and persisted.

**Architecture:** Service + Repository + Driver as defined in the spec. The middleware collects a raw payload from the request, hands it to the active dispatcher, and the dispatcher runs the pipeline (BotFilter → Enricher → Repositories) either inline (`sync`), async (`queue`), or after response (`defer`). Drivers are selected via `config('tracker.*')`.

**Tech Stack:** PHP 8.3, Laravel 12, `ozankurt/agent` for UA parsing, `geoip2/geoip2` (optional, for MaxMind driver), Pest 3, orchestra/testbench 10.

**Spec:** `docs/superpowers/specs/2026-04-10-ozankurt-tracker-design.md`

**Prerequisite:** Plan A complete (foundation merged to `main`). This plan builds on `feat/tracking-engine` branched from `main`.

---

## File Structure

Files created in this plan:

```
src/
├── Data/
│   └── Payload.php                            # request payload DTO
├── Repositories/
│   ├── RepositoryManager.php                  # aggregates all repos
│   ├── SessionRepository.php
│   ├── PageViewRepository.php
│   ├── EventRepository.php
│   └── GeoIpCacheRepository.php
├── Support/
│   ├── BotFilter.php
│   ├── PrivacyFilter.php
│   ├── VisitorCookie.php
│   ├── RefererParser.php
│   ├── Enricher.php
│   └── Pipeline.php
├── GeoIp/
│   ├── GeoIpProviderInterface.php
│   ├── GeoIpResult.php                        # DTO returned by providers
│   ├── GeoIpManager.php                       # factory → selects driver
│   ├── NullProvider.php
│   ├── MaxMindProvider.php
│   ├── IpInfoProvider.php
│   └── IpApiProvider.php
├── Dispatchers/
│   ├── DispatcherInterface.php
│   ├── DispatcherManager.php                  # factory → selects driver
│   ├── SyncDispatcher.php
│   ├── QueueDispatcher.php
│   └── DeferredDispatcher.php
├── Jobs/
│   └── ProcessTrackerPayload.php
├── Http/
│   └── Middleware/
│       └── TrackRequests.php
├── Tracker.php                                # REWRITE: real implementation
└── TrackerServiceProvider.php                 # UPDATE: bind new services

tests/
├── Unit/
│   ├── Data/
│   │   └── PayloadTest.php
│   ├── Repositories/
│   │   ├── SessionRepositoryTest.php
│   │   ├── PageViewRepositoryTest.php
│   │   ├── EventRepositoryTest.php
│   │   └── GeoIpCacheRepositoryTest.php
│   ├── Support/
│   │   ├── BotFilterTest.php
│   │   ├── PrivacyFilterTest.php
│   │   ├── VisitorCookieTest.php
│   │   ├── RefererParserTest.php
│   │   ├── EnricherTest.php
│   │   └── PipelineTest.php
│   ├── GeoIp/
│   │   ├── NullProviderTest.php
│   │   ├── IpApiProviderTest.php
│   │   ├── IpInfoProviderTest.php
│   │   └── GeoIpManagerTest.php
│   ├── Dispatchers/
│   │   ├── SyncDispatcherTest.php
│   │   ├── QueueDispatcherTest.php
│   │   ├── DeferredDispatcherTest.php
│   │   └── DispatcherManagerTest.php
│   └── TrackerServiceTest.php
└── Feature/
    ├── TrackRequestsMiddlewareTest.php        # end-to-end via sync dispatcher
    └── FacadeEndToEndTest.php                 # currentSession, onlineUsers, logEvent
```

Rationale:
- One class per file, one clear responsibility per class
- Tests mirror source structure for easy navigation
- Factories (`GeoIpManager`, `DispatcherManager`) isolate driver selection from call sites
- DTOs (`Payload`, `GeoIpResult`) make method signatures self-documenting and tests deterministic
- `Support/Pipeline.php` is the driver-agnostic processor — every dispatcher ultimately calls it

---

## Task 1: Payload DTO

**Files:**
- Create: `src/Data/Payload.php`
- Create: `tests/Unit/Data/PayloadTest.php`

A readonly value object representing a captured request, passed through the dispatcher and consumed by the pipeline. Serializable for queue drivers.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Data/PayloadTest.php`:

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Data\Payload;

it('constructs from an array and round-trips to array', function () {
    $data = [
        'ip'             => '203.0.113.5',
        'user_agent'     => 'Mozilla/5.0',
        'method'         => 'GET',
        'url'            => 'https://example.com/dashboard?tab=1',
        'path'           => '/dashboard',
        'route_name'     => 'dashboard',
        'route_action'   => 'App\Http\Controllers\DashboardController@index',
        'route_params'   => ['tab' => '1'],
        'query_params'   => ['tab' => '1'],
        'visitor_uuid'   => '11111111-1111-1111-1111-111111111111',
        'session_id'     => '22222222-2222-2222-2222-222222222222',
        'user_id'        => 42,
        'referer'        => 'https://google.com/search?q=tracker',
        'language_range' => 'en-US,en;q=0.9',
        'captured_at'    => '2026-04-10T12:00:00+00:00',
    ];

    $payload = Payload::fromArray($data);

    expect($payload->ip)->toBe('203.0.113.5')
        ->and($payload->userId)->toBe(42)
        ->and($payload->routeParams)->toBe(['tab' => '1'])
        ->and($payload->toArray())->toBe($data);
});

it('allows nullable fields', function () {
    $payload = Payload::fromArray([
        'ip'             => '203.0.113.5',
        'user_agent'     => 'Mozilla/5.0',
        'method'         => 'GET',
        'url'            => 'https://example.com/',
        'path'           => '/',
        'route_name'     => null,
        'route_action'   => null,
        'route_params'   => [],
        'query_params'   => [],
        'visitor_uuid'   => '11111111-1111-1111-1111-111111111111',
        'session_id'     => '22222222-2222-2222-2222-222222222222',
        'user_id'        => null,
        'referer'        => null,
        'language_range' => '',
        'captured_at'    => '2026-04-10T12:00:00+00:00',
    ]);

    expect($payload->userId)->toBeNull()
        ->and($payload->routeName)->toBeNull()
        ->and($payload->referer)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Data/PayloadTest.php`
Expected: FAIL with class-not-found.

- [ ] **Step 3: Write the Payload DTO**

Create `src/Data/Payload.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Data;

final class Payload
{
    /**
     * @param  array<string, mixed>  $routeParams
     * @param  array<string, mixed>  $queryParams
     */
    public function __construct(
        public readonly string $ip,
        public readonly string $userAgent,
        public readonly string $method,
        public readonly string $url,
        public readonly string $path,
        public readonly ?string $routeName,
        public readonly ?string $routeAction,
        public readonly array $routeParams,
        public readonly array $queryParams,
        public readonly string $visitorUuid,
        public readonly string $sessionId,
        public readonly int|string|null $userId,
        public readonly ?string $referer,
        public readonly string $languageRange,
        public readonly string $capturedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ip:            (string) $data['ip'],
            userAgent:     (string) $data['user_agent'],
            method:        (string) $data['method'],
            url:           (string) $data['url'],
            path:          (string) $data['path'],
            routeName:     $data['route_name'] ?? null,
            routeAction:   $data['route_action'] ?? null,
            routeParams:   (array) ($data['route_params'] ?? []),
            queryParams:   (array) ($data['query_params'] ?? []),
            visitorUuid:   (string) $data['visitor_uuid'],
            sessionId:     (string) $data['session_id'],
            userId:        $data['user_id'] ?? null,
            referer:       $data['referer'] ?? null,
            languageRange: (string) ($data['language_range'] ?? ''),
            capturedAt:    (string) $data['captured_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ip'             => $this->ip,
            'user_agent'     => $this->userAgent,
            'method'         => $this->method,
            'url'            => $this->url,
            'path'           => $this->path,
            'route_name'     => $this->routeName,
            'route_action'   => $this->routeAction,
            'route_params'   => $this->routeParams,
            'query_params'   => $this->queryParams,
            'visitor_uuid'   => $this->visitorUuid,
            'session_id'     => $this->sessionId,
            'user_id'        => $this->userId,
            'referer'        => $this->referer,
            'language_range' => $this->languageRange,
            'captured_at'    => $this->capturedAt,
        ];
    }
}
```

- [ ] **Step 4: Run test**

Run: `./vendor/bin/pest tests/Unit/Data/PayloadTest.php`
Expected: both tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Data/Payload.php tests/Unit/Data/PayloadTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add Payload DTO"
```

---

## Task 2: Repositories (4 repositories, one commit each)

Each repository is a thin wrapper around its Eloquent model, exposing exactly the operations the pipeline needs. Keeps persistence isolated from business logic and makes the pipeline trivially testable with fakes.

### Task 2a: SessionRepository

**Files:**
- Create: `src/Repositories/SessionRepository.php`
- Create: `tests/Unit/Repositories/SessionRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Repositories\SessionRepository;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

it('creates a session from attributes', function () {
    $repo = new SessionRepository();

    $session = $repo->create([
        'uuid'             => 'sess-1',
        'visitor_uuid'     => 'vis-1',
        'client_ip'        => '203.0.113.0',
        'user_agent'       => 'UA',
        'device_kind'      => 'desktop',
        'device_platform'  => 'macOS',
        'browser'          => 'Chrome',
        'browser_version'  => '120',
        'language'         => 'en',
        'language_range'   => 'en-US,en;q=0.9',
        'started_at'       => now(),
        'last_activity_at' => now(),
    ]);

    expect($session)->toBeInstanceOf(Session::class)
        ->and(Session::count())->toBe(1);
});

it('finds or creates a session by uuid (idempotent)', function () {
    $repo = new SessionRepository();

    $attrs = [
        'uuid'             => 'sess-dup',
        'visitor_uuid'     => 'vis-dup',
        'client_ip'        => '203.0.113.1',
        'user_agent'       => 'UA',
        'device_kind'      => 'desktop',
        'device_platform'  => 'Linux',
        'browser'          => 'Firefox',
        'browser_version'  => '125',
        'language'         => 'tr',
        'language_range'   => 'tr-TR',
        'started_at'       => now(),
        'last_activity_at' => now(),
    ];

    $first  = $repo->findOrCreateByUuid('sess-dup', $attrs);
    $second = $repo->findOrCreateByUuid('sess-dup', $attrs);

    expect($first->id)->toBe($second->id)
        ->and(Session::count())->toBe(1);
});

it('touches last_activity_at and increments page_views_count', function () {
    $repo = new SessionRepository();

    $session = $repo->create([
        'uuid'             => 'sess-touch',
        'visitor_uuid'     => 'vis-touch',
        'client_ip'        => '203.0.113.2',
        'user_agent'       => 'UA',
        'device_kind'      => 'desktop',
        'device_platform'  => 'Windows',
        'browser'          => 'Edge',
        'browser_version'  => '120',
        'language'         => 'en',
        'language_range'   => 'en-US',
        'started_at'       => now()->subMinutes(5),
        'last_activity_at' => now()->subMinutes(5),
    ]);

    $repo->touchActivity($session, pageViewDelta: 1, eventDelta: 0);
    $session->refresh();

    expect($session->page_views_count)->toBe(1)
        ->and($session->events_count)->toBe(0)
        ->and($session->last_activity_at->diffInMinutes(now()))->toBeLessThan(1);
});
```

- [ ] **Step 2: Run test → FAIL (class not found)**

- [ ] **Step 3: Write the repository**

Create `src/Repositories/SessionRepository.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Repositories;

use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Models\Session;

class SessionRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Session
    {
        return Session::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateByUuid(string $uuid, array $attributes): Session
    {
        $existing = Session::where('uuid', $uuid)->first();

        if ($existing !== null) {
            return $existing;
        }

        return Session::create(['uuid' => $uuid] + $attributes);
    }

    public function touchActivity(Session $session, int $pageViewDelta = 0, int $eventDelta = 0): void
    {
        $session->last_activity_at = Carbon::now();

        if ($pageViewDelta !== 0) {
            $session->page_views_count += $pageViewDelta;
        }

        if ($eventDelta !== 0) {
            $session->events_count += $eventDelta;
        }

        $session->save();
    }
}
```

- [ ] **Step 4: Run test → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Repositories/SessionRepository.php tests/Unit/Repositories/SessionRepositoryTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add SessionRepository"
```

### Task 2b: PageViewRepository

**Files:**
- Create: `src/Repositories/PageViewRepository.php`
- Create: `tests/Unit/Repositories/PageViewRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Repositories\PageViewRepository;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

function makeSessionForPageViewRepoTest(): Session
{
    return Session::create([
        'uuid'             => 'sess-pv-' . uniqid(),
        'visitor_uuid'     => 'vis-pv-' . uniqid(),
        'client_ip'        => '203.0.113.10',
        'user_agent'       => 'UA',
        'device_kind'      => 'desktop',
        'device_platform'  => 'macOS',
        'browser'          => 'Chrome',
        'browser_version'  => '120',
        'language'         => 'en',
        'language_range'   => 'en-US',
        'started_at'       => now(),
        'last_activity_at' => now(),
    ]);
}

it('creates a page view for a session', function () {
    $session = makeSessionForPageViewRepoTest();
    $repo = new PageViewRepository();

    $view = $repo->create([
        'session_id'   => $session->id,
        'method'       => 'GET',
        'path'         => '/dashboard',
        'route_name'   => 'dashboard',
        'route_action' => null,
        'route_params' => [],
        'query_params' => ['tab' => 'overview'],
        'status_code'  => 200,
        'duration_ms'  => 45,
        'created_at'   => now(),
    ]);

    expect($view)->toBeInstanceOf(PageView::class)
        ->and($view->session_id)->toBe($session->id)
        ->and($view->query_params)->toBe(['tab' => 'overview']);
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write the repository**

Create `src/Repositories/PageViewRepository.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Repositories;

use OzanKurt\Tracker\Models\PageView;

class PageViewRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PageView
    {
        return PageView::create($attributes);
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Repositories/PageViewRepository.php tests/Unit/Repositories/PageViewRepositoryTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add PageViewRepository"
```

### Task 2c: EventRepository

**Files:**
- Create: `src/Repositories/EventRepository.php`
- Create: `tests/Unit/Repositories/EventRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Repositories\EventRepository;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

it('creates an event for a session', function () {
    $session = Session::create([
        'uuid'             => 'sess-ev',
        'visitor_uuid'     => 'vis-ev',
        'client_ip'        => '203.0.113.20',
        'user_agent'       => 'UA',
        'device_kind'      => 'desktop',
        'device_platform'  => 'Linux',
        'browser'          => 'Chrome',
        'browser_version'  => '120',
        'language'         => 'en',
        'language_range'   => 'en-US',
        'started_at'       => now(),
        'last_activity_at' => now(),
    ]);

    $repo = new EventRepository();

    $event = $repo->create([
        'session_id' => $session->id,
        'name'       => 'signup.completed',
        'payload'    => ['plan' => 'pro'],
        'created_at' => now(),
    ]);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->name)->toBe('signup.completed')
        ->and($event->payload)->toBe(['plan' => 'pro']);
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write**

Create `src/Repositories/EventRepository.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Repositories;

use OzanKurt\Tracker\Models\Event;

class EventRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Event
    {
        return Event::create($attributes);
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Repositories/EventRepository.php tests/Unit/Repositories/EventRepositoryTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add EventRepository"
```

### Task 2d: GeoIpCacheRepository

**Files:**
- Create: `src/Repositories/GeoIpCacheRepository.php`
- Create: `tests/Unit/Repositories/GeoIpCacheRepositoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\GeoIpCache;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

it('returns null on cache miss', function () {
    $repo = new GeoIpCacheRepository();
    expect($repo->find('missing-hash'))->toBeNull();
});

it('stores and retrieves a cache entry', function () {
    $repo = new GeoIpCacheRepository();

    $repo->put('hash-1', [
        'country_code' => 'TR',
        'country_name' => 'Türkiye',
        'city'         => 'Istanbul',
        'latitude'     => 41.01,
        'longitude'    => 28.97,
    ], 'ipapi', ttlDays: 30);

    $entry = $repo->find('hash-1');
    expect($entry)->toBeInstanceOf(GeoIpCache::class)
        ->and($entry->country_code)->toBe('TR')
        ->and((float) $entry->latitude)->toBe(41.01);
});

it('ignores expired cache entries', function () {
    GeoIpCache::create([
        'ip_hash'      => 'expired-hash',
        'country_code' => 'US',
        'provider'     => 'ipapi',
        'cached_until' => now()->subDay(),
        'created_at'   => now()->subDays(10),
    ]);

    $repo = new GeoIpCacheRepository();
    expect($repo->find('expired-hash'))->toBeNull();
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write**

Create `src/Repositories/GeoIpCacheRepository.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Repositories;

use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Models\GeoIpCache;

class GeoIpCacheRepository
{
    public function find(string $ipHash): ?GeoIpCache
    {
        return GeoIpCache::where('ip_hash', $ipHash)
            ->where('cached_until', '>=', Carbon::now())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $geo
     */
    public function put(string $ipHash, array $geo, string $provider, int $ttlDays): GeoIpCache
    {
        return GeoIpCache::updateOrCreate(
            ['ip_hash' => $ipHash],
            [
                'country_code' => $geo['country_code'] ?? null,
                'country_name' => $geo['country_name'] ?? null,
                'city'         => $geo['city']         ?? null,
                'latitude'     => $geo['latitude']     ?? null,
                'longitude'    => $geo['longitude']    ?? null,
                'provider'     => $provider,
                'cached_until' => Carbon::now()->addDays($ttlDays),
                'created_at'   => Carbon::now(),
            ],
        );
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Repositories/GeoIpCacheRepository.php tests/Unit/Repositories/GeoIpCacheRepositoryTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add GeoIpCacheRepository"
```

### Task 2e: RepositoryManager (aggregator)

**Files:**
- Create: `src/Repositories/RepositoryManager.php`

A thin aggregator exposing all four repositories via a single object, bound as a singleton by the service provider. Makes wiring into the Pipeline cleaner (one dependency instead of four).

- [ ] **Step 1: Write RepositoryManager**

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Repositories;

class RepositoryManager
{
    public function __construct(
        public readonly SessionRepository $sessions,
        public readonly PageViewRepository $pageViews,
        public readonly EventRepository $events,
        public readonly GeoIpCacheRepository $geoIpCache,
    ) {}
}
```

No dedicated test — this class is a pure aggregator; any breakage will surface in the Pipeline tests.

- [ ] **Step 2: Commit**

```bash
git add src/Repositories/RepositoryManager.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add RepositoryManager aggregator"
```

---

## Task 3: Support classes

### Task 3a: VisitorCookie

**Files:**
- Create: `src/Support/VisitorCookie.php`
- Create: `tests/Unit/Support/VisitorCookieTest.php`

Reads/writes the long-lived visitor UUID cookie. Depends only on the cookie name and lifetime from config.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use OzanKurt\Tracker\Support\VisitorCookie;
use Symfony\Component\HttpFoundation\Cookie;

beforeEach(function () {
    config()->set('tracker.cookie.name', 'tracker_visitor');
    config()->set('tracker.cookie.lifetime_days', 365);
    config()->set('tracker.cookie.secure', true);
    config()->set('tracker.cookie.http_only', true);
    config()->set('tracker.cookie.same_site', 'lax');
});

it('generates a new uuid when the cookie is missing', function () {
    $request = Request::create('/');
    $cookie = new VisitorCookie();

    $uuid = $cookie->readOrIssue($request);

    expect($uuid)->toBeString()->toHaveLength(36)
        ->and($cookie->issuedCookie())->toBeInstanceOf(Cookie::class)
        ->and($cookie->issuedCookie()->getName())->toBe('tracker_visitor')
        ->and($cookie->issuedCookie()->getValue())->toBe($uuid);
});

it('reads an existing cookie and does not issue a new one', function () {
    $existing = '11111111-2222-3333-4444-555555555555';
    $request = Request::create('/');
    $request->cookies->set('tracker_visitor', $existing);

    $cookie = new VisitorCookie();
    $uuid = $cookie->readOrIssue($request);

    expect($uuid)->toBe($existing)
        ->and($cookie->issuedCookie())->toBeNull();
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write the VisitorCookie class**

Create `src/Support/VisitorCookie.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class VisitorCookie
{
    private ?Cookie $issuedCookie = null;

    public function readOrIssue(Request $request): string
    {
        $name = (string) config('tracker.cookie.name', 'tracker_visitor');
        $existing = $request->cookies->get($name);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $uuid = (string) Str::uuid();

        $this->issuedCookie = Cookie::create(
            name:     $name,
            value:    $uuid,
            expire:   time() + ((int) config('tracker.cookie.lifetime_days', 365) * 86400),
            path:     '/',
            domain:   null,
            secure:   (bool) config('tracker.cookie.secure', true),
            httpOnly: (bool) config('tracker.cookie.http_only', true),
            raw:      false,
            sameSite: (string) config('tracker.cookie.same_site', 'lax'),
        );

        return $uuid;
    }

    public function issuedCookie(): ?Cookie
    {
        return $this->issuedCookie;
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Support/VisitorCookie.php tests/Unit/Support/VisitorCookieTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add VisitorCookie support class"
```

### Task 3b: PrivacyFilter

**Files:**
- Create: `src/Support/PrivacyFilter.php`
- Create: `tests/Unit/Support/PrivacyFilterTest.php`

Decides whether a request should be tracked. Encapsulates: global enable/disable, ignored routes (glob), DNT header, opt-out cookie, bot heuristics (delegated to `BotFilter` in a later task — for now, just a constructor boolean the Pipeline can set).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use OzanKurt\Tracker\Support\PrivacyFilter;

beforeEach(function () {
    config()->set('tracker.enabled', true);
    config()->set('tracker.privacy.respect_dnt', true);
    config()->set('tracker.cookie.name', 'tracker_visitor');
    config()->set('tracker.routes.ignore', ['tracker/*', 'telescope/*']);
});

it('allows normal requests', function () {
    $filter = new PrivacyFilter();
    $request = Request::create('/dashboard');
    expect($filter->shouldTrack($request))->toBeTrue();
});

it('blocks when globally disabled', function () {
    config()->set('tracker.enabled', false);
    $filter = new PrivacyFilter();
    expect($filter->shouldTrack(Request::create('/dashboard')))->toBeFalse();
});

it('blocks ignored routes by glob', function () {
    $filter = new PrivacyFilter();
    expect($filter->shouldTrack(Request::create('/tracker/sessions')))->toBeFalse()
        ->and($filter->shouldTrack(Request::create('/telescope/requests')))->toBeFalse()
        ->and($filter->shouldTrack(Request::create('/dashboard')))->toBeTrue();
});

it('blocks when DNT header is 1 and respect_dnt is true', function () {
    $filter = new PrivacyFilter();
    $request = Request::create('/dashboard');
    $request->headers->set('DNT', '1');
    expect($filter->shouldTrack($request))->toBeFalse();
});

it('allows DNT when respect_dnt is false', function () {
    config()->set('tracker.privacy.respect_dnt', false);
    $filter = new PrivacyFilter();
    $request = Request::create('/dashboard');
    $request->headers->set('DNT', '1');
    expect($filter->shouldTrack($request))->toBeTrue();
});

it('blocks when the opt-out cookie is present', function () {
    $filter = new PrivacyFilter();
    $request = Request::create('/dashboard');
    $request->cookies->set('tracker_visitor_optout', '1');
    expect($filter->shouldTrack($request))->toBeFalse();
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write the class**

Create `src/Support/PrivacyFilter.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PrivacyFilter
{
    public function shouldTrack(Request $request): bool
    {
        if (! (bool) config('tracker.enabled', true)) {
            return false;
        }

        if ($this->isIgnoredRoute($request)) {
            return false;
        }

        if ($this->isDntRequest($request)) {
            return false;
        }

        if ($this->hasOptedOut($request)) {
            return false;
        }

        return true;
    }

    public function hasOptedOut(Request $request): bool
    {
        $cookieName = (string) config('tracker.cookie.name', 'tracker_visitor') . '_optout';

        return $request->cookies->has($cookieName);
    }

    private function isIgnoredRoute(Request $request): bool
    {
        /** @var array<int, string> $patterns */
        $patterns = (array) config('tracker.routes.ignore', []);
        $path = ltrim($request->path(), '/');

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function isDntRequest(Request $request): bool
    {
        if (! (bool) config('tracker.privacy.respect_dnt', true)) {
            return false;
        }

        return $request->headers->get('DNT') === '1';
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Support/PrivacyFilter.php tests/Unit/Support/PrivacyFilterTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add PrivacyFilter support class"
```

### Task 3c: BotFilter

**Files:**
- Create: `src/Support/BotFilter.php`
- Create: `tests/Unit/Support/BotFilterTest.php`

Delegates to `ozankurt/agent` for crawler detection. Uses Laravel's container so the agent instance is resolvable/mockable.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Support\BotFilter;

it('returns true for a known crawler user agent', function () {
    $filter = new BotFilter();
    // Googlebot is a known crawler recognized by all UA libraries
    expect($filter->isBot('Googlebot/2.1 (+http://www.google.com/bot.html)'))->toBeTrue();
});

it('returns false for a typical browser user agent', function () {
    $filter = new BotFilter();
    expect($filter->isBot('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Chrome/120.0.0.0 Safari/537.36'))->toBeFalse();
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write the class**

Create `src/Support/BotFilter.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

use OzanKurt\Agent\Agent;

class BotFilter
{
    public function isBot(string $userAgent): bool
    {
        return (new Agent())->isRobot($userAgent);
    }
}
```

**API reference**: `OzanKurt\Agent\Agent` extends `Detection\MobileDetect`. Constructor takes no UA (`__construct(?CacheInterface $cache = null, array $config = [])`). `isRobot($userAgent = null)` accepts the UA as an optional argument and returns a bool. Verified against `vendor/ozankurt/agent/src/Agent.php`.

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Support/BotFilter.php tests/Unit/Support/BotFilterTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add BotFilter backed by ozankurt/agent"
```

### Task 3d: RefererParser

**Files:**
- Create: `src/Support/RefererParser.php`
- Create: `tests/Unit/Support/RefererParserTest.php`

Extracts medium/source/search term from a referer URL. A simple heuristic parser — known search engines and social networks mapped to medium/source, everything else is `direct` or `internal`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Support\RefererParser;

it('parses a google search referer', function () {
    $parser = new RefererParser();
    $result = $parser->parse('https://www.google.com/search?q=ozankurt+tracker', 'example.com');

    expect($result->medium)->toBe('search')
        ->and($result->source)->toBe('google')
        ->and($result->searchTerm)->toBe('ozankurt tracker')
        ->and($result->url)->toBe('https://www.google.com/search?q=ozankurt+tracker')
        ->and($result->domain)->toBe('www.google.com');
});

it('parses a twitter social referer', function () {
    $parser = new RefererParser();
    $result = $parser->parse('https://t.co/abc123', 'example.com');

    expect($result->medium)->toBe('social')
        ->and($result->source)->toBe('twitter')
        ->and($result->searchTerm)->toBeNull();
});

it('returns null metadata for an internal referer (same host)', function () {
    $parser = new RefererParser();
    $result = $parser->parse('https://example.com/about', 'example.com');

    expect($result->medium)->toBe('internal')
        ->and($result->source)->toBeNull();
});

it('returns direct when referer is null or empty', function () {
    $parser = new RefererParser();
    $result = $parser->parse(null, 'example.com');

    expect($result->medium)->toBe('direct')
        ->and($result->url)->toBeNull();
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write the class**

Create `src/Support/RefererParser.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

final class RefererParser
{
    /** @var array<string, array{medium: string, source: string, search_param?: string}> */
    private const KNOWN_HOSTS = [
        'google.com'        => ['medium' => 'search',  'source' => 'google',      'search_param' => 'q'],
        'www.google.com'    => ['medium' => 'search',  'source' => 'google',      'search_param' => 'q'],
        'bing.com'          => ['medium' => 'search',  'source' => 'bing',        'search_param' => 'q'],
        'www.bing.com'      => ['medium' => 'search',  'source' => 'bing',        'search_param' => 'q'],
        'duckduckgo.com'    => ['medium' => 'search',  'source' => 'duckduckgo',  'search_param' => 'q'],
        'yandex.com'        => ['medium' => 'search',  'source' => 'yandex',      'search_param' => 'text'],
        'twitter.com'       => ['medium' => 'social',  'source' => 'twitter'],
        'x.com'             => ['medium' => 'social',  'source' => 'twitter'],
        't.co'              => ['medium' => 'social',  'source' => 'twitter'],
        'facebook.com'      => ['medium' => 'social',  'source' => 'facebook'],
        'www.facebook.com'  => ['medium' => 'social',  'source' => 'facebook'],
        'm.facebook.com'    => ['medium' => 'social',  'source' => 'facebook'],
        'linkedin.com'      => ['medium' => 'social',  'source' => 'linkedin'],
        'www.linkedin.com'  => ['medium' => 'social',  'source' => 'linkedin'],
        'reddit.com'        => ['medium' => 'social',  'source' => 'reddit'],
        'www.reddit.com'    => ['medium' => 'social',  'source' => 'reddit'],
        'news.ycombinator.com' => ['medium' => 'social', 'source' => 'hackernews'],
    ];

    public function parse(?string $refererUrl, string $currentHost): RefererResult
    {
        if ($refererUrl === null || $refererUrl === '') {
            return new RefererResult(
                url: null, domain: null, medium: 'direct', source: null, searchTerm: null,
            );
        }

        $host = parse_url($refererUrl, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return new RefererResult(
                url: $refererUrl, domain: null, medium: 'direct', source: null, searchTerm: null,
            );
        }

        if (strcasecmp($host, $currentHost) === 0) {
            return new RefererResult(
                url: $refererUrl, domain: $host, medium: 'internal', source: null, searchTerm: null,
            );
        }

        $lookup = self::KNOWN_HOSTS[strtolower($host)] ?? null;

        if ($lookup === null) {
            return new RefererResult(
                url: $refererUrl, domain: $host, medium: 'referral', source: $host, searchTerm: null,
            );
        }

        $searchTerm = null;
        if (isset($lookup['search_param'])) {
            $query = parse_url($refererUrl, PHP_URL_QUERY);
            if (is_string($query)) {
                parse_str($query, $parsed);
                $raw = $parsed[$lookup['search_param']] ?? null;
                if (is_string($raw) && $raw !== '') {
                    $searchTerm = $raw;
                }
            }
        }

        return new RefererResult(
            url: $refererUrl,
            domain: $host,
            medium: $lookup['medium'],
            source: $lookup['source'],
            searchTerm: $searchTerm,
        );
    }
}

final class RefererResult
{
    public function __construct(
        public readonly ?string $url,
        public readonly ?string $domain,
        public readonly string $medium,
        public readonly ?string $source,
        public readonly ?string $searchTerm,
    ) {}
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Support/RefererParser.php tests/Unit/Support/RefererParserTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add RefererParser with search/social/internal detection"
```

---

## Task 4: GeoIp layer

### Task 4a: GeoIpResult DTO and GeoIpProviderInterface

**Files:**
- Create: `src/GeoIp/GeoIpResult.php`
- Create: `src/GeoIp/GeoIpProviderInterface.php`

- [ ] **Step 1: Write both files**

`src/GeoIp/GeoIpResult.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

final class GeoIpResult
{
    public function __construct(
        public readonly ?string $countryCode,
        public readonly ?string $countryName,
        public readonly ?string $city,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'country_code' => $this->countryCode,
            'country_name' => $this->countryName,
            'city'         => $this->city,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
        ];
    }
}
```

`src/GeoIp/GeoIpProviderInterface.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

interface GeoIpProviderInterface
{
    public function lookup(string $ip): GeoIpResult;

    public function name(): string;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/GeoIp/GeoIpResult.php src/GeoIp/GeoIpProviderInterface.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add GeoIp DTO and provider interface"
```

### Task 4b: NullProvider

**Files:**
- Create: `src/GeoIp/NullProvider.php`
- Create: `tests/Unit/GeoIp/NullProviderTest.php`

- [ ] **Step 1: Write test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\GeoIp\NullProvider;

it('returns an empty result for any ip', function () {
    $provider = new NullProvider();
    $result = $provider->lookup('203.0.113.5');

    expect($result)->toBeInstanceOf(GeoIpResult::class)
        ->and($result->countryCode)->toBeNull()
        ->and($result->city)->toBeNull();
});

it('reports its name as null', function () {
    expect((new NullProvider())->name())->toBe('null');
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write**

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

final class NullProvider implements GeoIpProviderInterface
{
    public function lookup(string $ip): GeoIpResult
    {
        return GeoIpResult::empty();
    }

    public function name(): string
    {
        return 'null';
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/GeoIp/NullProvider.php tests/Unit/GeoIp/NullProviderTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add NullProvider geo-ip driver"
```

### Task 4c: IpApiProvider (HTTP)

**Files:**
- Create: `src/GeoIp/IpApiProvider.php`
- Create: `tests/Unit/GeoIp/IpApiProviderTest.php`

Uses `ip-api.com`'s free endpoint. Uses Laravel's HTTP client so it's fakeable.

- [ ] **Step 1: Write test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use OzanKurt\Tracker\GeoIp\IpApiProvider;

it('returns geo data on a successful lookup', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status'      => 'success',
            'countryCode' => 'TR',
            'country'     => 'Turkey',
            'city'        => 'Istanbul',
            'lat'         => 41.0082,
            'lon'         => 28.9784,
        ], 200),
    ]);

    $result = (new IpApiProvider())->lookup('203.0.113.5');

    expect($result->countryCode)->toBe('TR')
        ->and($result->countryName)->toBe('Turkey')
        ->and($result->city)->toBe('Istanbul')
        ->and($result->latitude)->toBe(41.0082)
        ->and($result->longitude)->toBe(28.9784);
});

it('returns an empty result when the api returns failure', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(['status' => 'fail', 'message' => 'reserved range'], 200),
    ]);

    $result = (new IpApiProvider())->lookup('10.0.0.1');

    expect($result->countryCode)->toBeNull();
});

it('returns an empty result on http error', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(null, 500),
    ]);

    $result = (new IpApiProvider())->lookup('203.0.113.5');
    expect($result->countryCode)->toBeNull();
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write**

Create `src/GeoIp/IpApiProvider.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

use Illuminate\Support\Facades\Http;
use Throwable;

final class IpApiProvider implements GeoIpProviderInterface
{
    public function lookup(string $ip): GeoIpResult
    {
        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,country,countryCode,city,lat,lon',
            ]);
        } catch (Throwable) {
            return GeoIpResult::empty();
        }

        if (! $response->successful()) {
            return GeoIpResult::empty();
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        if (($data['status'] ?? null) !== 'success') {
            return GeoIpResult::empty();
        }

        return new GeoIpResult(
            countryCode: isset($data['countryCode']) ? (string) $data['countryCode'] : null,
            countryName: isset($data['country']) ? (string) $data['country'] : null,
            city:        isset($data['city']) ? (string) $data['city'] : null,
            latitude:    isset($data['lat']) ? (float) $data['lat'] : null,
            longitude:   isset($data['lon']) ? (float) $data['lon'] : null,
        );
    }

    public function name(): string
    {
        return 'ipapi';
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/GeoIp/IpApiProvider.php tests/Unit/GeoIp/IpApiProviderTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add IpApiProvider geo-ip driver"
```

### Task 4d: IpInfoProvider (HTTP)

**Files:**
- Create: `src/GeoIp/IpInfoProvider.php`
- Create: `tests/Unit/GeoIp/IpInfoProviderTest.php`

Uses `ipinfo.io`. Requires an API token from config.

- [ ] **Step 1: Write test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use OzanKurt\Tracker\GeoIp\IpInfoProvider;

beforeEach(fn () => config()->set('tracker.geoip.ipinfo.token', 'fake-token'));

it('parses the ipinfo response shape', function () {
    Http::fake([
        'ipinfo.io/*' => Http::response([
            'ip'      => '203.0.113.5',
            'country' => 'TR',
            'city'    => 'Istanbul',
            'loc'     => '41.0082,28.9784',
        ], 200),
    ]);

    $result = (new IpInfoProvider())->lookup('203.0.113.5');

    expect($result->countryCode)->toBe('TR')
        ->and($result->city)->toBe('Istanbul')
        ->and($result->latitude)->toBe(41.0082)
        ->and($result->longitude)->toBe(28.9784);
});

it('returns empty on http failure', function () {
    Http::fake(['ipinfo.io/*' => Http::response(null, 500)]);
    expect((new IpInfoProvider())->lookup('203.0.113.5')->countryCode)->toBeNull();
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write**

Create `src/GeoIp/IpInfoProvider.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

use Illuminate\Support\Facades\Http;
use Throwable;

final class IpInfoProvider implements GeoIpProviderInterface
{
    public function lookup(string $ip): GeoIpResult
    {
        $token = (string) config('tracker.geoip.ipinfo.token', '');
        if ($token === '') {
            return GeoIpResult::empty();
        }

        try {
            $response = Http::timeout(3)->get("https://ipinfo.io/{$ip}", ['token' => $token]);
        } catch (Throwable) {
            return GeoIpResult::empty();
        }

        if (! $response->successful()) {
            return GeoIpResult::empty();
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        $lat = null;
        $lon = null;
        if (isset($data['loc']) && is_string($data['loc']) && str_contains($data['loc'], ',')) {
            [$latStr, $lonStr] = explode(',', $data['loc'], 2);
            $lat = (float) $latStr;
            $lon = (float) $lonStr;
        }

        return new GeoIpResult(
            countryCode: isset($data['country']) ? (string) $data['country'] : null,
            countryName: null,
            city:        isset($data['city']) ? (string) $data['city'] : null,
            latitude:    $lat,
            longitude:   $lon,
        );
    }

    public function name(): string
    {
        return 'ipinfo';
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/GeoIp/IpInfoProvider.php tests/Unit/GeoIp/IpInfoProviderTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add IpInfoProvider geo-ip driver"
```

### Task 4e: MaxMindProvider (optional, requires geoip2/geoip2)

**Files:**
- Create: `src/GeoIp/MaxMindProvider.php`

No test in Plan B — `geoip2/geoip2` is an optional composer suggest dependency and the MaxMind GeoLite2 database file isn't part of the repo. A smoke test requires infrastructure we don't have in CI. Its behavior is exercised indirectly via the `GeoIpManager` fallback test.

- [ ] **Step 1: Write the class**

Create `src/GeoIp/MaxMindProvider.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

use Throwable;

final class MaxMindProvider implements GeoIpProviderInterface
{
    public function lookup(string $ip): GeoIpResult
    {
        $databasePath = (string) config('tracker.geoip.maxmind.database', '');

        if ($databasePath === '' || ! is_readable($databasePath)) {
            return GeoIpResult::empty();
        }

        if (! class_exists(\GeoIp2\Database\Reader::class)) {
            return GeoIpResult::empty();
        }

        try {
            $reader = new \GeoIp2\Database\Reader($databasePath);
            $record = $reader->city($ip);
        } catch (Throwable) {
            return GeoIpResult::empty();
        }

        return new GeoIpResult(
            countryCode: $record->country->isoCode,
            countryName: $record->country->name,
            city:        $record->city->name,
            latitude:    $record->location->latitude !== null ? (float) $record->location->latitude : null,
            longitude:   $record->location->longitude !== null ? (float) $record->location->longitude : null,
        );
    }

    public function name(): string
    {
        return 'maxmind';
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/GeoIp/MaxMindProvider.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add MaxMindProvider geo-ip driver (optional)"
```

### Task 4f: GeoIpManager (driver factory + caching)

**Files:**
- Create: `src/GeoIp/GeoIpManager.php`
- Create: `tests/Unit/GeoIp/GeoIpManagerTest.php`

Selects the active driver by config, wraps lookups in the `GeoIpCacheRepository`. Call sites always use the manager; they never touch providers directly.

- [ ] **Step 1: Write test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\GeoIp\NullProvider;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

it('delegates to the active provider when the cache misses', function () {
    config()->set('tracker.geoip.driver', 'null');
    config()->set('tracker.geoip.cache_ttl_days', 30);

    $manager = new GeoIpManager(new GeoIpCacheRepository());
    $result = $manager->lookup('203.0.113.5');

    expect($result)->toBeInstanceOf(GeoIpResult::class)
        ->and($result->countryCode)->toBeNull(); // NullProvider returns empty
});

it('caches a non-empty result and returns it on subsequent lookups', function () {
    config()->set('tracker.geoip.driver', 'test-stub');
    config()->set('tracker.geoip.cache_ttl_days', 30);

    $callCount = 0;
    $stub = new class($callCount) implements \OzanKurt\Tracker\GeoIp\GeoIpProviderInterface {
        public function __construct(public int &$calls) {}
        public function lookup(string $ip): GeoIpResult
        {
            $this->calls++;
            return new GeoIpResult('TR', 'Türkiye', 'Istanbul', 41.01, 28.97);
        }
        public function name(): string { return 'test-stub'; }
    };

    $manager = new GeoIpManager(new GeoIpCacheRepository());
    $manager->setProviderOverride($stub);

    $first  = $manager->lookup('203.0.113.99');
    $second = $manager->lookup('203.0.113.99');

    expect($first->countryCode)->toBe('TR')
        ->and($second->countryCode)->toBe('TR')
        ->and($callCount)->toBe(1); // second call hit the cache
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write**

Create `src/GeoIp/GeoIpManager.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;

class GeoIpManager
{
    private ?GeoIpProviderInterface $override = null;

    public function __construct(
        private readonly GeoIpCacheRepository $cache,
    ) {}

    public function lookup(string $ip): GeoIpResult
    {
        $hash = hash('sha256', $ip);

        $cached = $this->cache->find($hash);
        if ($cached !== null) {
            return new GeoIpResult(
                countryCode: $cached->country_code,
                countryName: $cached->country_name,
                city:        $cached->city,
                latitude:    $cached->latitude !== null ? (float) $cached->latitude : null,
                longitude:   $cached->longitude !== null ? (float) $cached->longitude : null,
            );
        }

        $provider = $this->provider();
        $result = $provider->lookup($ip);

        if ($result->countryCode !== null) {
            $this->cache->put(
                ipHash: $hash,
                geo:    $result->toArray(),
                provider: $provider->name(),
                ttlDays: (int) config('tracker.geoip.cache_ttl_days', 30),
            );
        }

        return $result;
    }

    public function setProviderOverride(GeoIpProviderInterface $provider): void
    {
        $this->override = $provider;
    }

    private function provider(): GeoIpProviderInterface
    {
        if ($this->override !== null) {
            return $this->override;
        }

        return match ((string) config('tracker.geoip.driver', 'null')) {
            'maxmind' => new MaxMindProvider(),
            'ipinfo'  => new IpInfoProvider(),
            'ipapi'   => new IpApiProvider(),
            default   => new NullProvider(),
        };
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/GeoIp/GeoIpManager.php tests/Unit/GeoIp/GeoIpManagerTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add GeoIpManager factory with caching"
```

---

## Task 5: Enricher

**Files:**
- Create: `src/Support/Enricher.php`
- Create: `tests/Unit/Support/EnricherTest.php`

Transforms a `Payload` into a fully-attributed Session-ready array by running UA parsing, geo lookup, and referer parsing.

- [ ] **Step 1: Write test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\GeoIp\NullProvider;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;
use OzanKurt\Tracker\Support\Enricher;
use OzanKurt\Tracker\Support\RefererParser;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

it('enriches a payload with device, geo and referer data', function () {
    $geoManager = new GeoIpManager(new GeoIpCacheRepository());
    $geoManager->setProviderOverride(new class implements \OzanKurt\Tracker\GeoIp\GeoIpProviderInterface {
        public function lookup(string $ip): GeoIpResult {
            return new GeoIpResult('TR', 'Türkiye', 'Istanbul', 41.01, 28.97);
        }
        public function name(): string { return 'stub'; }
    });

    $enricher = new Enricher($geoManager, new RefererParser());

    $payload = Payload::fromArray([
        'ip'             => '203.0.113.50',
        'user_agent'     => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Chrome/120.0.0.0 Safari/537.36',
        'method'         => 'GET',
        'url'            => 'https://myapp.test/dashboard',
        'path'           => '/dashboard',
        'route_name'     => 'dashboard',
        'route_action'   => null,
        'route_params'   => [],
        'query_params'   => [],
        'visitor_uuid'   => '11111111-1111-1111-1111-111111111111',
        'session_id'     => '22222222-2222-2222-2222-222222222222',
        'user_id'        => null,
        'referer'        => 'https://www.google.com/search?q=ozankurt',
        'language_range' => 'en-US,en;q=0.9',
        'captured_at'    => '2026-04-10T12:00:00+00:00',
    ]);

    $data = $enricher->enrich($payload);

    expect($data['client_ip'])->toBe('203.0.113.50')
        ->and($data['browser'])->not->toBe('')
        ->and($data['country_code'])->toBe('TR')
        ->and($data['city'])->toBe('Istanbul')
        ->and($data['referer_medium'])->toBe('search')
        ->and($data['referer_source'])->toBe('google')
        ->and($data['referer_search_term'])->toBe('ozankurt')
        ->and($data['language'])->toBe('en-US');
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write**

Create `src/Support/Enricher.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

use OzanKurt\Agent\Agent;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\GeoIp\GeoIpManager;

class Enricher
{
    public function __construct(
        private readonly GeoIpManager $geoIp,
        private readonly RefererParser $refererParser,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function enrich(Payload $payload): array
    {
        $agent = new Agent();
        $agent->setUserAgent($payload->userAgent);

        $deviceKind = $this->resolveDeviceKind($agent, $payload->userAgent);
        $platform   = (string) ($agent->platform() ?: 'unknown');
        $browser    = (string) ($agent->browser() ?: 'unknown');

        $host = (string) (parse_url($payload->url, PHP_URL_HOST) ?: '');
        $referer = $this->refererParser->parse($payload->referer, $host);

        $geo = $this->geoIp->lookup($payload->ip);

        $language = $this->preferredLanguage($payload->languageRange);

        return [
            'uuid'             => $payload->sessionId,
            'visitor_uuid'     => $payload->visitorUuid,
            'user_id'          => $payload->userId,
            'client_ip'        => $payload->ip,
            'user_agent'       => $payload->userAgent,

            'device_kind'         => $deviceKind,
            'device_model'        => (string) ($agent->device() ?: null) ?: null,
            'device_platform'     => $platform,
            'device_platform_ver' => (string) ($agent->version($platform) ?: null) ?: null,
            'browser'             => $browser,
            'browser_version'     => (string) ($agent->version($browser) ?: 'unknown'),

            'language'       => $language,
            'language_range' => $payload->languageRange,

            'is_robot' => $agent->isRobot(),

            'country_code' => $geo->countryCode,
            'country_name' => $geo->countryName,
            'city'         => $geo->city,
            'latitude'     => $geo->latitude,
            'longitude'    => $geo->longitude,

            'referer_url'         => $referer->url,
            'referer_domain'      => $referer->domain,
            'referer_medium'      => $referer->medium,
            'referer_source'      => $referer->source,
            'referer_search_term' => $referer->searchTerm,

            'started_at'       => $payload->capturedAt,
            'last_activity_at' => $payload->capturedAt,
        ];
    }

    private function resolveDeviceKind(Agent $agent, string $userAgent): string
    {
        if ($agent->isRobot($userAgent)) {
            return 'bot';
        }
        if ($agent->isTablet()) {
            return 'tablet';
        }
        if ($agent->isMobile()) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function preferredLanguage(string $range): string
    {
        if ($range === '') {
            return 'unknown';
        }

        $first = explode(',', $range, 2)[0] ?? '';
        $first = trim(explode(';', $first, 2)[0]);

        return $first === '' ? 'unknown' : $first;
    }
}
```

**Note**: `ozankurt/agent` API method names (`platform`, `browser`, `version`, `device`, `isRobot`, `isTablet`, `isMobile`) are based on the Jenssegers agent lineage. If the actual package differs, adapt the method calls. Inspect `vendor/ozankurt/agent/src/Agent.php` to confirm. If a method is missing, use a reasonable fallback and add a comment.

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Support/Enricher.php tests/Unit/Support/EnricherTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add Enricher composing UA, geo and referer"
```

---

## Task 6: Pipeline

**Files:**
- Create: `src/Support/Pipeline.php`
- Create: `tests/Unit/Support/PipelineTest.php`

The driver-agnostic processor. Every dispatcher calls it. Takes a `Payload`, runs BotFilter → Enricher → persistence. Also handles event payloads via a separate `processEvent()` method for `Tracker::logEvent`.

- [ ] **Step 1: Write test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Repositories\EventRepository;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;
use OzanKurt\Tracker\Repositories\PageViewRepository;
use OzanKurt\Tracker\Repositories\RepositoryManager;
use OzanKurt\Tracker\Repositories\SessionRepository;
use OzanKurt\Tracker\Support\BotFilter;
use OzanKurt\Tracker\Support\Enricher;
use OzanKurt\Tracker\Support\Pipeline;
use OzanKurt\Tracker\Support\RefererParser;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    config()->set('tracker.privacy.drop_bots', true);
});

function makePipelineForPipelineTest(): Pipeline
{
    $geo = new GeoIpManager(new GeoIpCacheRepository());
    $geo->setProviderOverride(new class implements \OzanKurt\Tracker\GeoIp\GeoIpProviderInterface {
        public function lookup(string $ip): GeoIpResult { return GeoIpResult::empty(); }
        public function name(): string { return 'stub'; }
    });

    $repos = new RepositoryManager(
        sessions:  new SessionRepository(),
        pageViews: new PageViewRepository(),
        events:    new EventRepository(),
        geoIpCache: new GeoIpCacheRepository(),
    );

    return new Pipeline(
        botFilter: new BotFilter(),
        enricher:  new Enricher($geo, new RefererParser()),
        repositories: $repos,
    );
}

function makeBrowserPayload(string $sessionId = 'sess-1'): Payload
{
    return Payload::fromArray([
        'ip'             => '203.0.113.60',
        'user_agent'     => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36',
        'method'         => 'GET',
        'url'            => 'https://myapp.test/dashboard',
        'path'           => '/dashboard',
        'route_name'     => 'dashboard',
        'route_action'   => null,
        'route_params'   => [],
        'query_params'   => [],
        'visitor_uuid'   => '11111111-1111-1111-1111-111111111111',
        'session_id'     => $sessionId,
        'user_id'        => 7,
        'referer'        => null,
        'language_range' => 'en-US,en;q=0.9',
        'captured_at'    => now()->toIso8601String(),
    ]);
}

it('processes a browser request into a session and page view', function () {
    $pipeline = makePipelineForPipelineTest();
    $pipeline->process(makeBrowserPayload());

    expect(Session::count())->toBe(1)
        ->and(PageView::count())->toBe(1);

    $session = Session::first();
    expect($session->user_id)->toBe(7)
        ->and($session->page_views_count)->toBe(1);
});

it('reuses an existing session on subsequent page views', function () {
    $pipeline = makePipelineForPipelineTest();
    $pipeline->process(makeBrowserPayload());
    $pipeline->process(makeBrowserPayload());

    expect(Session::count())->toBe(1)
        ->and(PageView::count())->toBe(2);
    expect(Session::first()->page_views_count)->toBe(2);
});

it('drops bot requests when drop_bots is true', function () {
    $pipeline = makePipelineForPipelineTest();

    $payload = Payload::fromArray([
        'ip'             => '203.0.113.60',
        'user_agent'     => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        'method'         => 'GET',
        'url'            => 'https://myapp.test/',
        'path'           => '/',
        'route_name'     => null,
        'route_action'   => null,
        'route_params'   => [],
        'query_params'   => [],
        'visitor_uuid'   => '11111111-1111-1111-1111-111111111111',
        'session_id'     => 'sess-bot',
        'user_id'        => null,
        'referer'        => null,
        'language_range' => '',
        'captured_at'    => now()->toIso8601String(),
    ]);

    $pipeline->process($payload);

    expect(Session::count())->toBe(0)
        ->and(PageView::count())->toBe(0);
});

it('processes a custom event tied to the current session', function () {
    $pipeline = makePipelineForPipelineTest();
    $pipeline->process(makeBrowserPayload());
    $pipeline->processEvent(makeBrowserPayload(), 'signup.completed', ['plan' => 'pro']);

    expect(Event::count())->toBe(1);
    $event = Event::first();
    expect($event->name)->toBe('signup.completed')
        ->and($event->payload)->toBe(['plan' => 'pro'])
        ->and(Session::first()->events_count)->toBe(1);
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write**

Create `src/Support/Pipeline.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Repositories\RepositoryManager;

class Pipeline
{
    public function __construct(
        private readonly BotFilter $botFilter,
        private readonly Enricher $enricher,
        private readonly RepositoryManager $repositories,
    ) {}

    public function process(Payload $payload): void
    {
        if ($this->shouldDrop($payload)) {
            return;
        }

        $sessionAttrs = $this->enricher->enrich($payload);
        $session = $this->repositories->sessions->findOrCreateByUuid($payload->sessionId, $sessionAttrs);

        $this->repositories->pageViews->create([
            'session_id'   => $session->id,
            'method'       => $payload->method,
            'path'         => $payload->path,
            'route_name'   => $payload->routeName,
            'route_action' => $payload->routeAction,
            'route_params' => $payload->routeParams,
            'query_params' => $payload->queryParams,
            'status_code'  => null,
            'duration_ms'  => null,
            'created_at'   => Carbon::parse($payload->capturedAt),
        ]);

        $this->repositories->sessions->touchActivity($session, pageViewDelta: 1);
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function processEvent(Payload $payload, string $name, array $eventPayload): void
    {
        if ($this->shouldDrop($payload)) {
            return;
        }

        $sessionAttrs = $this->enricher->enrich($payload);
        $session = $this->repositories->sessions->findOrCreateByUuid($payload->sessionId, $sessionAttrs);

        $this->repositories->events->create([
            'session_id' => $session->id,
            'name'       => $name,
            'payload'    => $eventPayload,
            'created_at' => Carbon::parse($payload->capturedAt),
        ]);

        $this->repositories->sessions->touchActivity($session, eventDelta: 1);
    }

    private function shouldDrop(Payload $payload): bool
    {
        if ((bool) config('tracker.privacy.drop_bots', true) && $this->botFilter->isBot($payload->userAgent)) {
            return true;
        }

        return false;
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Support/Pipeline.php tests/Unit/Support/PipelineTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add processing Pipeline"
```

---

## Task 7: Dispatchers

### Task 7a: DispatcherInterface + SyncDispatcher

**Files:**
- Create: `src/Dispatchers/DispatcherInterface.php`
- Create: `src/Dispatchers/SyncDispatcher.php`
- Create: `tests/Unit/Dispatchers/SyncDispatcherTest.php`

- [ ] **Step 1: Write the interface**

`src/Dispatchers/DispatcherInterface.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Dispatchers;

use OzanKurt\Tracker\Data\Payload;

interface DispatcherInterface
{
    public function dispatchPageView(Payload $payload): void;

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function dispatchEvent(Payload $payload, string $name, array $eventPayload): void;

    /**
     * Called from terminable middleware to flush any work that was deferred
     * until after the response was sent. No-op for non-deferred drivers.
     */
    public function flush(): void;
}
```

- [ ] **Step 2: Write the sync dispatcher test**

`tests/Unit/Dispatchers/SyncDispatcherTest.php`:

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Dispatchers\SyncDispatcher;
use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Repositories\EventRepository;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;
use OzanKurt\Tracker\Repositories\PageViewRepository;
use OzanKurt\Tracker\Repositories\RepositoryManager;
use OzanKurt\Tracker\Repositories\SessionRepository;
use OzanKurt\Tracker\Support\BotFilter;
use OzanKurt\Tracker\Support\Enricher;
use OzanKurt\Tracker\Support\Pipeline;
use OzanKurt\Tracker\Support\RefererParser;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

function makeSyncPipeline(): Pipeline
{
    $geo = new GeoIpManager(new GeoIpCacheRepository());
    $geo->setProviderOverride(new class implements \OzanKurt\Tracker\GeoIp\GeoIpProviderInterface {
        public function lookup(string $ip): GeoIpResult { return GeoIpResult::empty(); }
        public function name(): string { return 'stub'; }
    });

    return new Pipeline(
        botFilter: new BotFilter(),
        enricher:  new Enricher($geo, new RefererParser()),
        repositories: new RepositoryManager(
            sessions:  new SessionRepository(),
            pageViews: new PageViewRepository(),
            events:    new EventRepository(),
            geoIpCache: new GeoIpCacheRepository(),
        ),
    );
}

it('processes the payload inline', function () {
    $dispatcher = new SyncDispatcher(makeSyncPipeline());

    $payload = Payload::fromArray([
        'ip' => '203.0.113.70', 'user_agent' => 'Mozilla/5.0 Chrome/120',
        'method' => 'GET', 'url' => 'https://app.test/', 'path' => '/',
        'route_name' => null, 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => 'sync-1',
        'user_id' => null, 'referer' => null,
        'language_range' => 'en-US', 'captured_at' => now()->toIso8601String(),
    ]);

    $dispatcher->dispatchPageView($payload);

    expect(Session::count())->toBe(1)
        ->and(PageView::count())->toBe(1);
});
```

Note: the pipeline is constructed manually (not via `app()`) because the service provider bindings for the tracking engine are added later in Task 8 (provider wiring). Unit tests should construct dependencies explicitly anyway.

- [ ] **Step 3: Write the sync dispatcher**

Create `src/Dispatchers/SyncDispatcher.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Dispatchers;

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Support\Pipeline;

final class SyncDispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly Pipeline $pipeline,
    ) {}

    public function dispatchPageView(Payload $payload): void
    {
        $this->pipeline->process($payload);
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function dispatchEvent(Payload $payload, string $name, array $eventPayload): void
    {
        $this->pipeline->processEvent($payload, $name, $eventPayload);
    }

    public function flush(): void
    {
        // Nothing to flush — sync dispatcher already processed inline.
    }
}
```

- [ ] **Step 4: Run → FAIL (if container not wired), then defer test until Task 11 wires pipeline; OR construct pipeline manually inline in test**

For the test in Step 2, if you don't yet have `Pipeline` bound in the container, rewrite the `beforeEach` to build the full pipeline manually (copy the `makePipelineForPipelineTest` helper from `PipelineTest`). Commit with the test in whichever form works against the current state. Task 11 will add the container binding.

- [ ] **Step 5: Commit**

```bash
git add src/Dispatchers/DispatcherInterface.php src/Dispatchers/SyncDispatcher.php tests/Unit/Dispatchers/SyncDispatcherTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add Dispatcher interface and SyncDispatcher"
```

### Task 7b: ProcessTrackerPayload job + QueueDispatcher

**Files:**
- Create: `src/Jobs/ProcessTrackerPayload.php`
- Create: `src/Dispatchers/QueueDispatcher.php`
- Create: `tests/Unit/Dispatchers/QueueDispatcherTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Unit/Dispatchers/QueueDispatcherTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Dispatchers\QueueDispatcher;
use OzanKurt\Tracker\Jobs\ProcessTrackerPayload;

it('pushes a ProcessTrackerPayload job on page view dispatch', function () {
    Queue::fake();

    $dispatcher = new QueueDispatcher();

    $payload = Payload::fromArray([
        'ip' => '203.0.113.80', 'user_agent' => 'UA',
        'method' => 'GET', 'url' => 'https://app.test/', 'path' => '/',
        'route_name' => null, 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => 'q-1', 'user_id' => null, 'referer' => null,
        'language_range' => '', 'captured_at' => now()->toIso8601String(),
    ]);

    $dispatcher->dispatchPageView($payload);

    Queue::assertPushed(ProcessTrackerPayload::class, function (ProcessTrackerPayload $job) use ($payload) {
        return $job->kind === 'page_view'
            && $job->payload['session_id'] === $payload->sessionId;
    });
});

it('pushes a ProcessTrackerPayload job on event dispatch', function () {
    Queue::fake();

    $dispatcher = new QueueDispatcher();

    $payload = Payload::fromArray([
        'ip' => '203.0.113.80', 'user_agent' => 'UA',
        'method' => 'GET', 'url' => 'https://app.test/', 'path' => '/',
        'route_name' => null, 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => 'q-1', 'user_id' => null, 'referer' => null,
        'language_range' => '', 'captured_at' => now()->toIso8601String(),
    ]);

    $dispatcher->dispatchEvent($payload, 'signup.completed', ['plan' => 'pro']);

    Queue::assertPushed(ProcessTrackerPayload::class, function (ProcessTrackerPayload $job) {
        return $job->kind === 'event' && $job->name === 'signup.completed';
    });
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write the job**

Create `src/Jobs/ProcessTrackerPayload.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Support\Pipeline;

class ProcessTrackerPayload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  'page_view'|'event'  $kind
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $eventPayload
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $payload,
        public readonly ?string $name = null,
        public readonly array $eventPayload = [],
    ) {}

    public function handle(Pipeline $pipeline): void
    {
        $payloadObj = Payload::fromArray($this->payload);

        if ($this->kind === 'event' && $this->name !== null) {
            $pipeline->processEvent($payloadObj, $this->name, $this->eventPayload);

            return;
        }

        $pipeline->process($payloadObj);
    }
}
```

- [ ] **Step 4: Write the dispatcher**

Create `src/Dispatchers/QueueDispatcher.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Dispatchers;

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Jobs\ProcessTrackerPayload;

final class QueueDispatcher implements DispatcherInterface
{
    public function dispatchPageView(Payload $payload): void
    {
        $this->dispatchJob(new ProcessTrackerPayload(
            kind: 'page_view',
            payload: $payload->toArray(),
        ));
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function dispatchEvent(Payload $payload, string $name, array $eventPayload): void
    {
        $this->dispatchJob(new ProcessTrackerPayload(
            kind: 'event',
            payload: $payload->toArray(),
            name: $name,
            eventPayload: $eventPayload,
        ));
    }

    public function flush(): void
    {
        // Nothing to flush for the queue dispatcher.
    }

    private function dispatchJob(ProcessTrackerPayload $job): void
    {
        $connection = config('tracker.queue.connection');
        $queue      = (string) config('tracker.queue.name', 'default');

        $pending = ProcessTrackerPayload::dispatch(
            $job->kind, $job->payload, $job->name, $job->eventPayload,
        )->onQueue($queue);

        if (is_string($connection) && $connection !== '') {
            $pending->onConnection($connection);
        }
    }
}
```

- [ ] **Step 5: Run → PASS**

- [ ] **Step 6: Commit**

```bash
git add src/Jobs/ProcessTrackerPayload.php src/Dispatchers/QueueDispatcher.php tests/Unit/Dispatchers/QueueDispatcherTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add ProcessTrackerPayload job and QueueDispatcher"
```

### Task 7c: DeferredDispatcher

**Files:**
- Create: `src/Dispatchers/DeferredDispatcher.php`
- Create: `tests/Unit/Dispatchers/DeferredDispatcherTest.php`

Stashes payloads until `flush()` is called (from terminable middleware). Runs the pipeline inline at flush time.

- [ ] **Step 1: Write test**

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Dispatchers\DeferredDispatcher;
use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Repositories\EventRepository;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;
use OzanKurt\Tracker\Repositories\PageViewRepository;
use OzanKurt\Tracker\Repositories\RepositoryManager;
use OzanKurt\Tracker\Repositories\SessionRepository;
use OzanKurt\Tracker\Support\BotFilter;
use OzanKurt\Tracker\Support\Enricher;
use OzanKurt\Tracker\Support\Pipeline;
use OzanKurt\Tracker\Support\RefererParser;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

function makeDeferredPipeline(): Pipeline
{
    $geo = new GeoIpManager(new GeoIpCacheRepository());
    $geo->setProviderOverride(new class implements \OzanKurt\Tracker\GeoIp\GeoIpProviderInterface {
        public function lookup(string $ip): GeoIpResult { return GeoIpResult::empty(); }
        public function name(): string { return 'stub'; }
    });

    return new Pipeline(
        botFilter: new BotFilter(),
        enricher:  new Enricher($geo, new RefererParser()),
        repositories: new RepositoryManager(
            sessions:  new SessionRepository(),
            pageViews: new PageViewRepository(),
            events:    new EventRepository(),
            geoIpCache: new GeoIpCacheRepository(),
        ),
    );
}

it('does not process until flush is called', function () {
    $dispatcher = new DeferredDispatcher(makeDeferredPipeline());

    $payload = Payload::fromArray([
        'ip' => '203.0.113.90', 'user_agent' => 'Mozilla/5.0 Chrome/120',
        'method' => 'GET', 'url' => 'https://app.test/', 'path' => '/',
        'route_name' => null, 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => 'def-1', 'user_id' => null, 'referer' => null,
        'language_range' => 'en-US', 'captured_at' => now()->toIso8601String(),
    ]);

    $dispatcher->dispatchPageView($payload);

    expect(Session::count())->toBe(0); // not yet processed

    $dispatcher->flush();

    expect(Session::count())->toBe(1)
        ->and(PageView::count())->toBe(1);
});
```

Pipeline is constructed manually here (same reason as Task 7a).

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write**

Create `src/Dispatchers/DeferredDispatcher.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Dispatchers;

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Support\Pipeline;

final class DeferredDispatcher implements DispatcherInterface
{
    /** @var list<array{kind: 'page_view'|'event', payload: Payload, name?: string, eventPayload?: array<string, mixed>}> */
    private array $queue = [];

    public function __construct(
        private readonly Pipeline $pipeline,
    ) {}

    public function dispatchPageView(Payload $payload): void
    {
        $this->queue[] = ['kind' => 'page_view', 'payload' => $payload];
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function dispatchEvent(Payload $payload, string $name, array $eventPayload): void
    {
        $this->queue[] = [
            'kind' => 'event',
            'payload' => $payload,
            'name' => $name,
            'eventPayload' => $eventPayload,
        ];
    }

    public function flush(): void
    {
        foreach ($this->queue as $entry) {
            if ($entry['kind'] === 'page_view') {
                $this->pipeline->process($entry['payload']);
                continue;
            }

            $this->pipeline->processEvent(
                $entry['payload'],
                $entry['name'] ?? '',
                $entry['eventPayload'] ?? [],
            );
        }

        $this->queue = [];
    }
}
```

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Dispatchers/DeferredDispatcher.php tests/Unit/Dispatchers/DeferredDispatcherTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add DeferredDispatcher"
```

### Task 7d: DispatcherManager (factory)

**Files:**
- Create: `src/Dispatchers/DispatcherManager.php`

- [ ] **Step 1: Write the manager**

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Dispatchers;

use Illuminate\Contracts\Foundation\Application;
use OzanKurt\Tracker\Support\Pipeline;

class DispatcherManager
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function driver(): DispatcherInterface
    {
        return match ((string) config('tracker.dispatcher', 'queue')) {
            'sync'  => new SyncDispatcher($this->app->make(Pipeline::class)),
            'defer' => $this->app->make(DeferredDispatcher::class),
            default => new QueueDispatcher(),
        };
    }
}
```

Note: `DeferredDispatcher` must be a singleton so the middleware can call `flush()` on the same instance in `terminate()`. Task 11 wires this as a singleton in the service provider.

- [ ] **Step 2: Commit**

```bash
git add src/Dispatchers/DispatcherManager.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add DispatcherManager factory"
```

---

## Task 8: TrackRequests middleware

**Files:**
- Create: `src/Http/Middleware/TrackRequests.php`
- Create: `tests/Feature/TrackRequestsMiddlewareTest.php`

The entry point. Runs the privacy filter, resolves the visitor cookie, builds the payload, calls the dispatcher. Implements `TerminableMiddleware` semantics (a `terminate()` method) so the deferred driver can flush after response.

**Execution order note:** This task's feature test uses `$this->get('/demo')` which routes through Laravel's HTTP stack, resolving the middleware from the container. That requires the service provider bindings from **Task 11** to be in place. The executor controller should commit Task 11 BEFORE running this task's test. Two acceptable paths:

1. Execute Task 11 (provider wiring) first, then return to Task 8 and run its test (preferred)
2. Commit this task's middleware code without running its test, then after Task 11, return to this test file and run it

If the controller is a subagent-driven workflow, the plan controller should reorder these two tasks so provider wiring commits first.

- [ ] **Step 1: Write the feature test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OzanKurt\Tracker\Http\Middleware\TrackRequests;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    config()->set('tracker.dispatcher', 'sync');
    config()->set('tracker.privacy.drop_bots', true);

    Route::middleware(TrackRequests::class)->get('/demo', fn () => 'ok')->name('demo');
});

it('records a session and page view for a normal request', function () {
    $response = $this->withServerVariables([
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36',
    ])->get('/demo');

    $response->assertOk()->assertSee('ok');

    expect(Session::count())->toBe(1)
        ->and(PageView::count())->toBe(1);

    $view = PageView::first();
    expect($view->path)->toBe('/demo');
});

it('does not record when tracker is disabled', function () {
    config()->set('tracker.enabled', false);

    $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120'])->get('/demo');

    expect(Session::count())->toBe(0);
});

it('skips ignored routes', function () {
    config()->set('tracker.routes.ignore', ['demo']);

    $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120'])->get('/demo');

    expect(Session::count())->toBe(0);
});
```

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Write the middleware**

Create `src/Http/Middleware/TrackRequests.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Dispatchers\DispatcherManager;
use OzanKurt\Tracker\Support\PrivacyFilter;
use OzanKurt\Tracker\Support\VisitorCookie;
use Symfony\Component\HttpFoundation\Response;

class TrackRequests
{
    public function __construct(
        private readonly Application $app,
        private readonly PrivacyFilter $privacy,
        private readonly VisitorCookie $visitor,
        private readonly DispatcherManager $dispatchers,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->privacy->shouldTrack($request)) {
            return $next($request);
        }

        $visitorUuid = $this->visitor->readOrIssue($request);
        $sessionId   = $this->resolveSessionId($request, $visitorUuid);

        $payload = Payload::fromArray([
            'ip'             => (string) ($request->ip() ?? ''),
            'user_agent'     => (string) $request->userAgent(),
            'method'         => $request->getMethod(),
            'url'            => $request->fullUrl(),
            'path'           => '/' . ltrim($request->path(), '/'),
            'route_name'     => optional($request->route())->getName(),
            'route_action'   => optional($request->route())->getActionName(),
            'route_params'   => optional($request->route())->parameters() ?? [],
            'query_params'   => $request->query(),
            'visitor_uuid'   => $visitorUuid,
            'session_id'     => $sessionId,
            'user_id'        => optional($request->user())->getAuthIdentifier(),
            'referer'        => $request->headers->get('referer'),
            'language_range' => (string) $request->headers->get('accept-language', ''),
            'captured_at'    => Carbon::now()->toIso8601String(),
        ]);

        $this->dispatchers->driver()->dispatchPageView($payload);

        /** @var Response $response */
        $response = $next($request);

        $issued = $this->visitor->issuedCookie();
        if ($issued !== null) {
            $response->headers->setCookie($issued);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if ((string) config('tracker.dispatcher', 'queue') === 'defer') {
            $this->dispatchers->driver()->flush();
        }
    }

    private function resolveSessionId(Request $request, string $visitorUuid): string
    {
        if ($request->hasSession()) {
            $session = $request->session();
            $key = 'tracker.session_uuid';
            $existing = $session->get($key);
            if (is_string($existing) && $existing !== '') {
                return $existing;
            }
            $generated = (string) Str::uuid();
            $session->put($key, $generated);

            return $generated;
        }

        // Fall back to visitor-scoped session id when no Laravel session.
        return $visitorUuid;
    }
}
```

- [ ] **Step 4: Run → FAIL (container not wired)**

The test will fail until Task 11 binds dependencies in the service provider. Expected — leave the test in place.

- [ ] **Step 5: Commit**

```bash
git add src/Http/Middleware/TrackRequests.php tests/Feature/TrackRequestsMiddlewareTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add TrackRequests middleware"
```

---

## Task 9: Real Tracker service

**Files:**
- Modify: `src/Tracker.php` (replace the stub)
- Create: `tests/Unit/TrackerServiceTest.php`

Replace the stub with the full facade target: `currentSession`, `sessions`, `onlineUsers`, `users`, `pageViews`, `events`, `logEvent`, `enable`/`disable`, `optOut`/`optIn`/`hasOptedOut`, `sessionId`/`visitorId`.

**Execution order note:** Same ordering concern as Task 8. The test uses `app(Tracker::class)` which will fail until Task 11's service provider wiring removes the old closure binding that instantiates `new Tracker()` with no args. Controller should commit Task 11 before running this task's test, OR the controller should update the provider's `Tracker::class` binding inline in this task to `$this->app->singleton(Tracker::class)` (letting Laravel auto-wire).

- [ ] **Step 1: Write test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use OzanKurt\Tracker\Jobs\ProcessTrackerPayload;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Tracker;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations'));

it('returns online users filtered by the last activity window', function () {
    Session::create([
        'uuid' => 'recent', 'visitor_uuid' => 'v1', 'client_ip' => '1.1.1.1',
        'user_agent' => 'UA', 'device_kind' => 'desktop', 'device_platform' => 'macOS',
        'browser' => 'Chrome', 'browser_version' => '120',
        'language' => 'en', 'language_range' => 'en-US',
        'started_at' => now()->subMinutes(1), 'last_activity_at' => now()->subMinutes(1),
    ]);
    Session::create([
        'uuid' => 'stale', 'visitor_uuid' => 'v2', 'client_ip' => '2.2.2.2',
        'user_agent' => 'UA', 'device_kind' => 'desktop', 'device_platform' => 'Windows',
        'browser' => 'Firefox', 'browser_version' => '125',
        'language' => 'tr', 'language_range' => 'tr-TR',
        'started_at' => now()->subHours(2), 'last_activity_at' => now()->subHours(2),
    ]);

    $online = (app(Tracker::class))->onlineUsers(3);

    expect($online)->toHaveCount(1)
        ->and($online->first()->uuid)->toBe('recent');
});

it('logs an event via the active dispatcher', function () {
    config()->set('tracker.dispatcher', 'queue');
    Queue::fake();

    app(Tracker::class)->logEvent('signup.completed', ['plan' => 'pro']);

    Queue::assertPushed(ProcessTrackerPayload::class, function (ProcessTrackerPayload $job) {
        return $job->kind === 'event' && $job->name === 'signup.completed';
    });
});
```

Note: `logEvent()` needs to construct a `Payload` from the current request context. If no request is active (as in this test), it should fall back to a synthetic payload with placeholder values. See the implementation for how.

- [ ] **Step 2: Run → FAIL**

- [ ] **Step 3: Rewrite `src/Tracker.php`**

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Dispatchers\DispatcherManager;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Support\VisitorCookie;

class Tracker
{
    private bool $enabled = true;

    public function __construct(
        private readonly DispatcherManager $dispatchers,
        private readonly VisitorCookie $visitor,
    ) {}

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled && (bool) config('tracker.enabled', true);
    }

    public function currentSession(): ?Session
    {
        $uuid = $this->sessionId();
        if ($uuid === null) {
            return null;
        }

        return Session::where('uuid', $uuid)->first();
    }

    public function sessionId(): ?string
    {
        $request = request();
        if (! $request instanceof Request || ! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get('tracker.session_uuid');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function visitorId(): ?string
    {
        $request = request();
        if (! $request instanceof Request) {
            return null;
        }

        $name = (string) config('tracker.cookie.name', 'tracker_visitor');
        $value = $request->cookies->get($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return Collection<int, Session>
     */
    public function sessions(int $minutes = 1440): Collection
    {
        return Session::where('last_activity_at', '>=', Carbon::now()->subMinutes($minutes))
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * @return Collection<int, Session>
     */
    public function onlineUsers(int $minutes = 3): Collection
    {
        return $this->sessions($minutes);
    }

    /**
     * @return Collection<int, Session>
     */
    public function users(int $minutes = 1440): Collection
    {
        return Session::whereNotNull('user_id')
            ->where('last_activity_at', '>=', Carbon::now()->subMinutes($minutes))
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * @return Collection<int, PageView>
     */
    public function pageViews(int $minutes = 1440): Collection
    {
        return PageView::where('created_at', '>=', Carbon::now()->subMinutes($minutes))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Event>
     */
    public function events(int $minutes = 1440, ?string $name = null): Collection
    {
        $query = Event::where('created_at', '>=', Carbon::now()->subMinutes($minutes));
        if ($name !== null) {
            $query->where('name', $name);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function logEvent(string $name, array $payload = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->dispatchers->driver()->dispatchEvent(
            $this->payloadFromContext(),
            $name,
            $payload,
        );
    }

    public function optOut(): void
    {
        $name = (string) config('tracker.cookie.name', 'tracker_visitor') . '_optout';
        Cookie::queue(Cookie::make(
            name: $name,
            value: '1',
            minutes: (int) config('tracker.cookie.lifetime_days', 365) * 24 * 60,
        ));
    }

    public function optIn(): void
    {
        $name = (string) config('tracker.cookie.name', 'tracker_visitor') . '_optout';
        Cookie::queue(Cookie::forget($name));
    }

    public function hasOptedOut(): bool
    {
        $request = request();
        if (! $request instanceof Request) {
            return false;
        }
        $name = (string) config('tracker.cookie.name', 'tracker_visitor') . '_optout';

        return $request->cookies->has($name);
    }

    private function payloadFromContext(): Payload
    {
        $request = request() instanceof Request ? request() : null;
        $now = Carbon::now()->toIso8601String();

        if ($request === null) {
            return Payload::fromArray([
                'ip' => '0.0.0.0', 'user_agent' => 'cli',
                'method' => 'CLI', 'url' => 'cli://tracker', 'path' => '/',
                'route_name' => null, 'route_action' => null,
                'route_params' => [], 'query_params' => [],
                'visitor_uuid' => (string) Str::uuid(),
                'session_id' => (string) Str::uuid(),
                'user_id' => null, 'referer' => null,
                'language_range' => '', 'captured_at' => $now,
            ]);
        }

        $visitorUuid = $this->visitor->readOrIssue($request);
        $sessionId = $this->sessionId() ?? $visitorUuid;

        return Payload::fromArray([
            'ip'             => (string) ($request->ip() ?? '0.0.0.0'),
            'user_agent'     => (string) $request->userAgent(),
            'method'         => $request->getMethod(),
            'url'            => $request->fullUrl(),
            'path'           => '/' . ltrim($request->path(), '/'),
            'route_name'     => optional($request->route())->getName(),
            'route_action'   => optional($request->route())->getActionName(),
            'route_params'   => optional($request->route())->parameters() ?? [],
            'query_params'   => $request->query(),
            'visitor_uuid'   => $visitorUuid,
            'session_id'     => $sessionId,
            'user_id'        => optional($request->user())->getAuthIdentifier(),
            'referer'        => $request->headers->get('referer'),
            'language_range' => (string) $request->headers->get('accept-language', ''),
            'captured_at'    => $now,
        ]);
    }
}
```

- [ ] **Step 4: Run → FAIL (container not wired yet)**

Expected — leave the test. Task 11 wires container bindings.

- [ ] **Step 5: Commit**

```bash
git add src/Tracker.php tests/Unit/TrackerServiceTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): replace stub Tracker service with real facade implementation"
```

---

## Task 10: Extend Tracker Facade with docblocks

**Files:**
- Modify: `src/Facades/Tracker.php`

Add `@method` docblocks to improve IDE autocompletion and Larastan inference.

- [ ] **Step 1: Rewrite the facade**

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

/**
 * @method static bool isEnabled()
 * @method static void enable()
 * @method static void disable()
 * @method static ?Session currentSession()
 * @method static ?string sessionId()
 * @method static ?string visitorId()
 * @method static Collection<int, Session> sessions(int $minutes = 1440)
 * @method static Collection<int, Session> onlineUsers(int $minutes = 3)
 * @method static Collection<int, Session> users(int $minutes = 1440)
 * @method static Collection<int, PageView> pageViews(int $minutes = 1440)
 * @method static Collection<int, Event> events(int $minutes = 1440, ?string $name = null)
 * @method static void logEvent(string $name, array<string, mixed> $payload = [])
 * @method static void optOut()
 * @method static void optIn()
 * @method static bool hasOptedOut()
 *
 * @see \OzanKurt\Tracker\Tracker
 */
class Tracker extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \OzanKurt\Tracker\Tracker::class;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Facades/Tracker.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "docs(tracker): add method docblocks to Tracker facade"
```

---

## Task 11: Wire the TrackerServiceProvider

**Files:**
- Modify: `src/TrackerServiceProvider.php`

Register all the new singletons and ensure `DeferredDispatcher` is a singleton so `terminate()` can flush.

- [ ] **Step 1: Rewrite the service provider**

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker;

use Illuminate\Support\ServiceProvider;
use OzanKurt\Tracker\Dispatchers\DeferredDispatcher;
use OzanKurt\Tracker\Dispatchers\DispatcherManager;
use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\Repositories\EventRepository;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;
use OzanKurt\Tracker\Repositories\PageViewRepository;
use OzanKurt\Tracker\Repositories\RepositoryManager;
use OzanKurt\Tracker\Repositories\SessionRepository;
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

        // Main service
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
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```

- [ ] **Step 2: Run the full suite**

```bash
./vendor/bin/pest
```

Expected: ALL tests from Plan B pass now. Any still-failing tests should be diagnosed and fixed. If the number of tests is significantly lower than expected, check that the new test files are being discovered.

If `./vendor/bin/pest` reports failures, address them. Common issues:
- Container resolution failing → verify bindings in the service provider match the class names in the test `app(...)` calls
- Middleware `terminate()` not being called → in testbench, `$this->get()` triggers terminate automatically

- [ ] **Step 3: Commit**

```bash
git add src/TrackerServiceProvider.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): wire all tracking engine services in the provider"
```

---

## Task 12: Feature test — end-to-end facade

**Files:**
- Create: `tests/Feature/FacadeEndToEndTest.php`

Verifies the public API (facade) behaves correctly end-to-end over a simulated HTTP request.

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OzanKurt\Tracker\Facades\Tracker;
use OzanKurt\Tracker\Http\Middleware\TrackRequests;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    config()->set('tracker.dispatcher', 'sync');

    Route::middleware([\Illuminate\Session\Middleware\StartSession::class, TrackRequests::class])
        ->get('/hello', function () {
            Tracker::logEvent('hello.viewed', ['source' => 'test']);
            return 'hi';
        });
});

it('tracks a session, page view and logged event via the facade', function () {
    $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120'])
        ->get('/hello')
        ->assertOk();

    expect(\OzanKurt\Tracker\Models\Session::count())->toBe(1)
        ->and(\OzanKurt\Tracker\Models\PageView::count())->toBe(1)
        ->and(\OzanKurt\Tracker\Models\Event::count())->toBe(1);

    $event = \OzanKurt\Tracker\Models\Event::first();
    expect($event->name)->toBe('hello.viewed')
        ->and($event->payload)->toBe(['source' => 'test']);
});
```

- [ ] **Step 2: Run → PASS**

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/FacadeEndToEndTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "test(tracker): end-to-end facade via sync dispatcher"
```

---

## Task 13: Green check — pest + phpstan + pint

- [ ] **Step 1: Full pest run**

```bash
./vendor/bin/pest
```

Expected: all tests pass. Record the count in your report.

- [ ] **Step 2: Phpstan level 8**

```bash
./vendor/bin/phpstan analyse --no-progress
```

Expected: `[OK] No errors`. If errors surface:
- Generic type hints missing on relations / collections → add `/** @return SomeClass<T1, T2> */` docblocks
- `mixed` type on config/request helpers → narrow with explicit `(string) $value` casts
- Unknown classes from `ozankurt/agent` → add a `bootstrapFiles` line in `phpstan.neon` pointing at `vendor/ozankurt/agent/src/Agent.php` if needed

Fix errors inline with explicit typing rather than lowering the level or adding ignores.

- [ ] **Step 3: Pint**

```bash
./vendor/bin/pint --test
```

Expected: clean. If not, run `./vendor/bin/pint` to auto-fix, re-run the full test suite to confirm nothing regressed, and add the fixes in a style commit.

- [ ] **Step 4: Commit if fixes were required**

```bash
git add -u
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "chore(tracker): finalize tracking engine — pint + phpstan clean"
```

---

## Definition of Done

Plan B is complete when:

- All 12 tasks committed
- Full `./vendor/bin/pest` green (Plan A tests + all Plan B tests, ~30+ tests total)
- `./vendor/bin/phpstan analyse` level 8 clean
- `./vendor/bin/pint --test` clean
- Registering `TrackRequests` middleware on a Laravel 12 app, setting `TRACKER_DISPATCHER=sync`, and hitting a route results in a row in `tracker_sessions` and `tracker_page_views`
- `Tracker::logEvent()` records an event row
- `Tracker::onlineUsers()`, `Tracker::sessions()`, `Tracker::pageViews()`, `Tracker::events()` return the expected data
- Queue, sync, and deferred dispatchers all have passing unit tests

After this, Plan C (Dashboard & Release) can be written — it adds `TrackerStats`, the Blade dashboard, retention purge command, and GitHub Actions CI.
