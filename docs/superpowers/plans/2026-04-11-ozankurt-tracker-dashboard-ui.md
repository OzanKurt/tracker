# ozankurt/tracker — Plan C-2: Dashboard UI

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the bundled admin dashboard — server-rendered Blade + Tailwind + Alpine.js + Chart.js. A working UI for browsing sessions, page views, events, and aggregate stats. Mounted under `/tracker` and protected by the `Authorize` middleware from Plan C-1.

**Architecture:** Blade views served by controllers in `src/Http/Controllers/Dashboard/`. Routes registered from `routes/dashboard.php` (loaded conditionally by the service provider when `tracker.dashboard.enabled = true`). Assets delivered via **Tailwind CDN + Alpine CDN + Chart.js CDN** — no npm build step required by the package or its consumers. A single Blade layout (`tracker::layout`) is extended by every page.

**Tech Stack:** Laravel 12 Blade, Tailwind CSS (via CDN), Alpine.js (via CDN), Chart.js (via CDN). No new composer dependencies.

**Spec:** `docs/superpowers/specs/2026-04-10-ozankurt-tracker-design.md` (section: Admin Dashboard)

**Prerequisite:** Plan C-1 complete on `main` (`TrackerStats` service + `Authorize` middleware). Work on `feat/dashboard-ui`.

---

## Scope decisions

**In:** Overview page, sessions list (paginated, filterable), session detail page (with timeline), page views list, events list, per-user sessions list.

**Out:** Custom admin authentication (rely on the host app's auth + `viewTracker` gate), JSON API endpoints, real-time updates, CSV/JSON export, user settings, dark mode (nice-to-have — add if simple).

**CDN vs local assets:** Shipping with CDN links for v1. No build pipeline needed, no `resources/dist/` to maintain. Pinned versions for reproducibility. Dashboard requires internet during page loads — acceptable tradeoff for a package serving internal admin tools. Can switch to local assets in a later revision.

---

## File Structure

Files created:

```
routes/
└── dashboard.php                              # dashboard routes (GET /tracker, etc)

resources/
└── views/
    ├── layout.blade.php                       # tracker::layout (Tailwind+Alpine+Chart.js shell)
    ├── partials/
    │   ├── nav.blade.php                      # top nav with page links
    │   ├── stat-card.blade.php                # reusable stat card
    │   └── session-row.blade.php              # reusable session table row
    └── pages/
        ├── overview.blade.php                 # GET /tracker
        ├── sessions/
        │   ├── index.blade.php                # GET /tracker/sessions
        │   └── show.blade.php                 # GET /tracker/sessions/{uuid}
        ├── page-views.blade.php               # GET /tracker/page-views
        ├── events.blade.php                   # GET /tracker/events
        └── users/
            └── show.blade.php                 # GET /tracker/users/{id}

src/
└── Http/
    └── Controllers/
        └── Dashboard/
            ├── OverviewController.php
            ├── SessionsController.php
            ├── PageViewsController.php
            ├── EventsController.php
            └── UsersController.php

tests/
└── Feature/
    └── Dashboard/
        ├── OverviewPageTest.php
        ├── SessionsPageTest.php
        ├── SessionDetailPageTest.php
        ├── PageViewsPageTest.php
        ├── EventsPageTest.php
        ├── UserSessionsPageTest.php
        └── DashboardGateTest.php               # 403 when gate denies
```

Files modified:

```
src/TrackerServiceProvider.php                 # load routes, load views, register view namespace
```

---

## Task 1: Routes file + service provider wiring + base layout

Smallest viable skeleton: a single route renders a bare layout that says "Tracker Dashboard" so we can confirm everything is wired before filling in the pages.

**Files:**
- Create: `routes/dashboard.php`
- Create: `resources/views/layout.blade.php`
- Create: `resources/views/pages/overview.blade.php` (placeholder)
- Create: `src/Http/Controllers/Dashboard/OverviewController.php`
- Modify: `src/TrackerServiceProvider.php`
- Create: `tests/Feature/Dashboard/DashboardGateTest.php`

- [ ] **Step 1: Write the gate-denial feature test (TDD)**

Create `tests/Feature/Dashboard/DashboardGateTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
});

it('renders the overview page when the viewTracker gate allows', function () {
    Gate::define('viewTracker', fn ($user = null) => true);

    $this->get('/tracker')
        ->assertOk()
        ->assertSee('Tracker');
});

it('returns 403 when the viewTracker gate denies', function () {
    Gate::define('viewTracker', fn ($user = null) => false);

    $this->get('/tracker')->assertForbidden();
});
```

- [ ] **Step 2: Write the controller**

Create `src/Http/Controllers/Dashboard/OverviewController.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;

class OverviewController
{
    public function __invoke(): View
    {
        return view('tracker::pages.overview');
    }
}
```

- [ ] **Step 3: Write the routes file**

Create `routes/dashboard.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OzanKurt\Tracker\Http\Controllers\Dashboard\OverviewController;

Route::get('/', OverviewController::class)->name('tracker.overview');
```

- [ ] **Step 4: Write the layout**

Create `resources/views/layout.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Tracker') — {{ config('app.name', 'Laravel') }}</title>

    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script defer src="https://unpkg.com/alpinejs@3.14.3/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <link href="https://rsms.me/inter/inter.css" rel="stylesheet">
</head>
<body class="h-full font-sans text-slate-800 antialiased">
    <div class="min-h-full">
        @include('tracker::partials.nav')

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            @yield('content')
        </main>
    </div>
</body>
</html>
```

- [ ] **Step 5: Write the nav partial**

Create `resources/views/partials/nav.blade.php`:

```blade
<nav class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-8">
                <a href="{{ route('tracker.overview') }}" class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                    <svg class="size-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z" />
                    </svg>
                    Tracker
                </a>
                <div class="flex gap-1 text-sm">
                    @php
                        $navItems = [
                            ['route' => 'tracker.overview', 'label' => 'Overview'],
                            ['route' => 'tracker.sessions.index', 'label' => 'Sessions'],
                            ['route' => 'tracker.page-views', 'label' => 'Page views'],
                            ['route' => 'tracker.events', 'label' => 'Events'],
                        ];
                    @endphp
                    @foreach ($navItems as $item)
                        @if (\Illuminate\Support\Facades\Route::has($item['route']))
                            <a href="{{ route($item['route']) }}"
                               class="rounded-md px-3 py-1.5 transition {{ request()->routeIs($item['route']) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">
                                {{ $item['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</nav>
```

Note: the nav uses `Route::has(...)` guards so pages that don't yet exist don't break the nav. As we add routes in later tasks, they become active automatically.

- [ ] **Step 6: Write the placeholder overview page**

Create `resources/views/pages/overview.blade.php`:

```blade
@extends('tracker::layout')

@section('title', 'Overview')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Overview</h1>
        <p class="mt-1 text-sm text-slate-600">Visitor analytics for the last 24 hours.</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Overview content coming in Task 2.
    </div>
@endsection
```

- [ ] **Step 7: Wire the service provider**

Edit `src/TrackerServiceProvider.php`. Add to the `boot()` method (outside the `runningInConsole()` guard):

```php
// Load views
$this->loadViewsFrom(__DIR__.'/../resources/views', 'tracker');

// Register dashboard routes when enabled
if ((bool) config('tracker.dashboard.enabled', true)) {
    \Illuminate\Support\Facades\Route::group([
        'prefix'     => (string) config('tracker.dashboard.path', 'tracker'),
        'middleware' => array_merge(
            (array) config('tracker.dashboard.middleware', ['web']),
            [\OzanKurt\Tracker\Http\Middleware\Authorize::class],
        ),
        'as'         => 'tracker.',
    ], function () {
        $this->loadRoutesFrom(__DIR__.'/../routes/dashboard.php');
    });
}

// Publish views
if ($this->app->runningInConsole()) {
    $this->publishes([
        __DIR__.'/../resources/views' => resource_path('views/vendor/tracker'),
    ], 'tracker-views');
}
```

Note: the existing `publishes()` calls for config and migrations stay inside the `runningInConsole()` guard. The new `tracker-views` publish also goes in that guard. Route registration and view loading happen unconditionally.

- [ ] **Step 8: Update the dashboard test's route group name**

In the test, `Route::has('tracker.overview')` (the route name set via the group's `as` prefix + `tracker.overview`) should resolve. The controller route name `tracker.overview` becomes `tracker.overview` (the group prefix is `tracker.` and the route name is `overview`... wait that gives `tracker.overview`, which matches).

Actually the routes file above declares `Route::get('/', OverviewController::class)->name('tracker.overview');` — but inside a group with `as => 'tracker.'`, the full name becomes `tracker.tracker.overview`. Fix: drop the `tracker.` prefix from the route name in the file since the group adds it.

Rewrite `routes/dashboard.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OzanKurt\Tracker\Http\Controllers\Dashboard\OverviewController;

Route::get('/', OverviewController::class)->name('overview');
```

With the group's `as => 'tracker.'`, the full name is `tracker.overview`.

- [ ] **Step 9: Run the test**

```bash
./vendor/bin/pest tests/Feature/Dashboard/DashboardGateTest.php
```

Expected: 2 tests pass.

- [ ] **Step 10: Commit**

```bash
git add routes/dashboard.php \
        resources/views/layout.blade.php \
        resources/views/partials/nav.blade.php \
        resources/views/pages/overview.blade.php \
        src/Http/Controllers/Dashboard/OverviewController.php \
        src/TrackerServiceProvider.php \
        tests/Feature/Dashboard/DashboardGateTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add dashboard skeleton (routes, layout, overview placeholder)"
```

---

## Task 2: Overview page with stats + charts

Fill in the overview page with real `TrackerStats` integration: 4 stat cards (visitors, sessions, page views, online users), a line chart of sessions-over-time, and top-5 lists for countries/browsers/pages.

**Files:**
- Modify: `src/Http/Controllers/Dashboard/OverviewController.php`
- Modify: `resources/views/pages/overview.blade.php`
- Create: `resources/views/partials/stat-card.blade.php`
- Create: `tests/Feature/Dashboard/OverviewPageTest.php`

- [ ] **Step 1: Write the feature test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    Gate::define('viewTracker', fn ($user = null) => true);
});

function seedOverviewData(): void
{
    $s1 = Session::create([
        'uuid' => 'o-1', 'visitor_uuid' => 'v-1', 'client_ip' => '1.2.3.4',
        'user_agent' => 'UA', 'device_kind' => 'desktop', 'device_platform' => 'macOS',
        'browser' => 'Chrome', 'browser_version' => '120',
        'language' => 'en', 'language_range' => 'en-US',
        'country_code' => 'TR', 'country_name' => 'Türkiye',
        'started_at' => now()->subMinutes(5), 'last_activity_at' => now()->subMinutes(1),
    ]);
    PageView::create([
        'session_id' => $s1->id, 'method' => 'GET', 'path' => '/home',
        'route_name' => 'home', 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'status_code' => 200, 'duration_ms' => 20,
        'created_at' => now()->subMinutes(5),
    ]);
    PageView::create([
        'session_id' => $s1->id, 'method' => 'GET', 'path' => '/home',
        'route_name' => 'home', 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'status_code' => 200, 'duration_ms' => 20,
        'created_at' => now()->subMinutes(2),
    ]);
}

it('renders the overview page with stats and charts', function () {
    seedOverviewData();

    $response = $this->get('/tracker');

    $response->assertOk()
        ->assertSee('Overview')
        ->assertSee('Unique visitors')
        ->assertSee('Sessions')
        ->assertSee('Page views')
        ->assertSee('Top pages')
        ->assertSee('Top countries')
        ->assertSee('Top browsers')
        ->assertSee('/home')
        ->assertSee('Türkiye')
        ->assertSee('Chrome');
});
```

- [ ] **Step 2: Write the controller**

Replace `src/Http/Controllers/Dashboard/OverviewController.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Stats\TrackerStats;

class OverviewController
{
    public function __invoke(TrackerStats $stats): View
    {
        $since = Carbon::now()->subDay();

        return view('tracker::pages.overview', [
            'uniqueVisitors'   => $stats->uniqueVisitors($since),
            'sessionsCount'    => \OzanKurt\Tracker\Models\Session::where('started_at', '>=', $since)->count(),
            'pageViewsCount'   => \OzanKurt\Tracker\Models\PageView::where('created_at', '>=', $since)->count(),
            'onlineCount'      => \OzanKurt\Tracker\Models\Session::where('last_activity_at', '>=', Carbon::now()->subMinutes(3))->count(),
            'topPages'         => $stats->topPages($since, 5),
            'topCountries'     => $stats->topCountries($since, 5),
            'topBrowsers'      => $stats->topBrowsers($since, 5),
            'sessionsOverTime' => $stats->sessionsOverTime($since, 'hour'),
        ]);
    }
}
```

- [ ] **Step 3: Write the stat-card partial**

Create `resources/views/partials/stat-card.blade.php`:

```blade
@props(['label', 'value', 'sublabel' => null])

<div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <dt class="text-sm font-medium text-slate-500">{{ $label }}</dt>
    <dd class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $value }}</dd>
    @if ($sublabel)
        <dd class="mt-1 text-xs text-slate-500">{{ $sublabel }}</dd>
    @endif
</div>
```

- [ ] **Step 4: Replace the overview view**

Replace `resources/views/pages/overview.blade.php`:

```blade
@extends('tracker::layout')

@section('title', 'Overview')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Overview</h1>
            <p class="mt-1 text-sm text-slate-600">Visitor analytics for the last 24 hours.</p>
        </div>
    </div>

    {{-- Stat cards --}}
    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @include('tracker::partials.stat-card', ['label' => 'Unique visitors', 'value' => number_format($uniqueVisitors)])
        @include('tracker::partials.stat-card', ['label' => 'Sessions', 'value' => number_format($sessionsCount)])
        @include('tracker::partials.stat-card', ['label' => 'Page views', 'value' => number_format($pageViewsCount)])
        @include('tracker::partials.stat-card', ['label' => 'Online now', 'value' => number_format($onlineCount), 'sublabel' => 'last 3 minutes'])
    </dl>

    {{-- Sessions over time chart --}}
    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Sessions over time</h2>
        <div class="mt-4 h-64">
            <canvas id="sessions-chart"
                    data-labels="{{ json_encode($sessionsOverTime->pluck('bucket')) }}"
                    data-values="{{ json_encode($sessionsOverTime->pluck('sessions')) }}"></canvas>
        </div>
    </div>

    {{-- Top N lists --}}
    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Top pages</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($topPages as $row)
                    <li class="flex items-center justify-between px-5 py-3">
                        <span class="truncate text-slate-700">{{ $row->path }}</span>
                        <span class="ml-2 tabular-nums text-slate-900">{{ number_format((int) $row->views) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-3 text-slate-500">No data.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Top countries</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($topCountries as $row)
                    <li class="flex items-center justify-between px-5 py-3">
                        <span class="text-slate-700">{{ $row->country_name ?? $row->country_code }}</span>
                        <span class="ml-2 tabular-nums text-slate-900">{{ number_format((int) $row->sessions) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-3 text-slate-500">No data.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Top browsers</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($topBrowsers as $row)
                    <li class="flex items-center justify-between px-5 py-3">
                        <span class="text-slate-700">{{ $row->browser }}</span>
                        <span class="ml-2 tabular-nums text-slate-900">{{ number_format((int) $row->sessions) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-3 text-slate-500">No data.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('sessions-chart');
            if (!canvas) return;

            const labels = JSON.parse(canvas.dataset.labels || '[]');
            const values = JSON.parse(canvas.dataset.values || '[]');

            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sessions',
                        data: values,
                        borderColor: 'rgb(79 70 229)',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { grid: { display: false } },
                    },
                },
            });
        });
    </script>
@endsection
```

- [ ] **Step 5: Run the test**

```bash
./vendor/bin/pest tests/Feature/Dashboard/OverviewPageTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Http/Controllers/Dashboard/OverviewController.php \
        resources/views/pages/overview.blade.php \
        resources/views/partials/stat-card.blade.php \
        tests/Feature/Dashboard/OverviewPageTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add dashboard overview with stats cards and chart"
```

---

## Task 3: Sessions listing page

Paginated list of sessions with basic filters (date range, country, device kind).

**Files:**
- Create: `src/Http/Controllers/Dashboard/SessionsController.php`
- Create: `resources/views/pages/sessions/index.blade.php`
- Create: `tests/Feature/Dashboard/SessionsPageTest.php`
- Modify: `routes/dashboard.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    Gate::define('viewTracker', fn ($user = null) => true);
});

function seedListSession(array $overrides = []): Session
{
    return Session::create(array_merge([
        'uuid'             => 'list-' . uniqid(),
        'visitor_uuid'     => 'v-' . uniqid(),
        'client_ip'        => '203.0.113.1',
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

it('renders the sessions listing page with session rows', function () {
    seedListSession(['uuid' => 'sess-list-1', 'client_ip' => '203.0.113.99']);
    seedListSession(['uuid' => 'sess-list-2', 'country_code' => 'US', 'country_name' => 'United States']);

    $this->get('/tracker/sessions')
        ->assertOk()
        ->assertSee('Sessions')
        ->assertSee('sess-list-1')
        ->assertSee('sess-list-2')
        ->assertSee('Türkiye')
        ->assertSee('United States');
});

it('filters sessions by country_code', function () {
    seedListSession(['uuid' => 'tr-1', 'country_code' => 'TR', 'country_name' => 'Türkiye']);
    seedListSession(['uuid' => 'us-1', 'country_code' => 'US', 'country_name' => 'United States']);

    $this->get('/tracker/sessions?country=US')
        ->assertOk()
        ->assertSee('us-1')
        ->assertDontSee('tr-1');
});

it('filters sessions by device_kind', function () {
    seedListSession(['uuid' => 'desk-1', 'device_kind' => 'desktop']);
    seedListSession(['uuid' => 'mob-1', 'device_kind' => 'mobile']);

    $this->get('/tracker/sessions?device=mobile')
        ->assertOk()
        ->assertSee('mob-1')
        ->assertDontSee('desk-1');
});
```

- [ ] **Step 2: Write the controller**

Create `src/Http/Controllers/Dashboard/SessionsController.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use OzanKurt\Tracker\Models\Session;

class SessionsController
{
    public function index(Request $request): View
    {
        $query = Session::query()->orderByDesc('last_activity_at');

        if ($country = $request->query('country')) {
            $query->where('country_code', (string) $country);
        }

        if ($device = $request->query('device')) {
            $query->where('device_kind', (string) $device);
        }

        if ($browser = $request->query('browser')) {
            $query->where('browser', (string) $browser);
        }

        $sessions = $query->paginate(25)->withQueryString();

        return view('tracker::pages.sessions.index', [
            'sessions' => $sessions,
            'filters'  => [
                'country' => $request->query('country'),
                'device'  => $request->query('device'),
                'browser' => $request->query('browser'),
            ],
        ]);
    }
}
```

- [ ] **Step 3: Write the view**

Create `resources/views/pages/sessions/index.blade.php`:

```blade
@extends('tracker::layout')

@section('title', 'Sessions')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Sessions</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $sessions->total() }} total</p>
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <input type="text" name="country" placeholder="Country code (e.g. TR)" value="{{ $filters['country'] }}"
               class="rounded-md border-slate-300 text-sm">
        <select name="device" class="rounded-md border-slate-300 text-sm">
            <option value="">Any device</option>
            @foreach (['desktop', 'mobile', 'tablet', 'bot'] as $kind)
                <option value="{{ $kind }}" @selected($filters['device'] === $kind)>{{ ucfirst($kind) }}</option>
            @endforeach
        </select>
        <input type="text" name="browser" placeholder="Browser" value="{{ $filters['browser'] }}"
               class="rounded-md border-slate-300 text-sm">
        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
            Filter
        </button>
        <a href="{{ route('tracker.sessions.index') }}" class="rounded-md px-4 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
            Reset
        </a>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Session</th>
                    <th class="px-4 py-3">Started</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3">Country</th>
                    <th class="px-4 py-3">Device</th>
                    <th class="px-4 py-3">Browser</th>
                    <th class="px-4 py-3">Views</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sessions as $session)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('tracker.sessions.show', $session->uuid) }}" class="font-mono text-xs text-indigo-600 hover:text-indigo-800">
                                {{ $session->uuid }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $session->started_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $session->client_ip }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $session->country_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $session->device_kind }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $session->browser }} {{ $session->browser_version }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-900">{{ $session->page_views_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No sessions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $sessions->links() }}
    </div>
@endsection
```

- [ ] **Step 4: Add the route**

Edit `routes/dashboard.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OzanKurt\Tracker\Http\Controllers\Dashboard\OverviewController;
use OzanKurt\Tracker\Http\Controllers\Dashboard\SessionsController;

Route::get('/', OverviewController::class)->name('overview');
Route::get('/sessions', [SessionsController::class, 'index'])->name('sessions.index');
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/pest tests/Feature/Dashboard/SessionsPageTest.php
```

Expected: 3 tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Http/Controllers/Dashboard/SessionsController.php \
        resources/views/pages/sessions/index.blade.php \
        routes/dashboard.php \
        tests/Feature/Dashboard/SessionsPageTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add dashboard sessions listing page"
```

---

## Task 4: Session detail page

Click through from the listing to see a full session: visitor info, device/geo/referer, and a chronological timeline of page views + events.

**Files:**
- Modify: `src/Http/Controllers/Dashboard/SessionsController.php` (add `show` method)
- Create: `resources/views/pages/sessions/show.blade.php`
- Create: `tests/Feature/Dashboard/SessionDetailPageTest.php`
- Modify: `routes/dashboard.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    Gate::define('viewTracker', fn ($user = null) => true);
});

it('renders the session detail page with timeline', function () {
    $session = Session::create([
        'uuid'             => 'detail-1',
        'visitor_uuid'     => 'v-detail-1',
        'client_ip'        => '203.0.113.77',
        'user_agent'       => 'Mozilla/5.0',
        'device_kind'      => 'desktop',
        'device_platform'  => 'macOS',
        'browser'          => 'Chrome',
        'browser_version'  => '120',
        'language'         => 'en',
        'language_range'   => 'en-US',
        'country_code'     => 'TR',
        'country_name'     => 'Türkiye',
        'city'             => 'Istanbul',
        'started_at'       => now()->subMinutes(10),
        'last_activity_at' => now()->subMinutes(1),
    ]);

    PageView::create([
        'session_id' => $session->id, 'method' => 'GET', 'path' => '/dashboard',
        'route_name' => 'dashboard', 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'status_code' => 200, 'duration_ms' => 15,
        'created_at' => now()->subMinutes(10),
    ]);
    Event::create([
        'session_id' => $session->id, 'name' => 'feature.used',
        'payload' => ['feature' => 'export'], 'created_at' => now()->subMinutes(5),
    ]);

    $this->get("/tracker/sessions/{$session->uuid}")
        ->assertOk()
        ->assertSee('detail-1')
        ->assertSee('Istanbul')
        ->assertSee('Türkiye')
        ->assertSee('Chrome')
        ->assertSee('/dashboard')
        ->assertSee('feature.used');
});

it('returns 404 for a missing session uuid', function () {
    $this->get('/tracker/sessions/nonexistent')->assertNotFound();
});
```

- [ ] **Step 2: Add the show method to SessionsController**

Append to `src/Http/Controllers/Dashboard/SessionsController.php`:

```php
    public function show(string $uuid): View
    {
        $session = Session::where('uuid', $uuid)->firstOrFail();

        $pageViews = $session->pageViews()->orderBy('created_at')->get();
        $events    = $session->events()->orderBy('created_at')->get();

        // Merge into a single chronological timeline
        $timeline = collect()
            ->merge($pageViews->map(fn ($pv) => [
                'type'  => 'page_view',
                'at'    => $pv->created_at,
                'label' => $pv->method.' '.$pv->path,
                'meta'  => $pv->route_name,
            ]))
            ->merge($events->map(fn ($ev) => [
                'type'  => 'event',
                'at'    => $ev->created_at,
                'label' => $ev->name,
                'meta'  => $ev->payload ? json_encode($ev->payload) : null,
            ]))
            ->sortBy('at')
            ->values();

        return view('tracker::pages.sessions.show', [
            'session'  => $session,
            'timeline' => $timeline,
        ]);
    }
```

- [ ] **Step 3: Write the view**

Create `resources/views/pages/sessions/show.blade.php`:

```blade
@extends('tracker::layout')

@section('title', 'Session '.$session->uuid)

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-mono text-lg text-slate-900">{{ $session->uuid }}</h1>
            <p class="mt-1 text-sm text-slate-600">Started {{ $session->started_at->diffForHumans() }}</p>
        </div>
        <a href="{{ route('tracker.sessions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to sessions</a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Visitor info --}}
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Visitor</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Visitor ID</dt>
                    <dd class="truncate font-mono text-xs text-slate-700">{{ $session->visitor_uuid }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">User ID</dt>
                    <dd class="text-slate-700">{{ $session->user_id ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">IP</dt>
                    <dd class="font-mono text-xs text-slate-700">{{ $session->client_ip }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Language</dt>
                    <dd class="text-slate-700">{{ $session->language }}</dd>
                </div>
            </dl>
        </div>

        {{-- Device --}}
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Device</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Kind</dt>
                    <dd class="text-slate-700">{{ $session->device_kind }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Platform</dt>
                    <dd class="text-slate-700">{{ $session->device_platform }} {{ $session->device_platform_ver }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Browser</dt>
                    <dd class="text-slate-700">{{ $session->browser }} {{ $session->browser_version }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Bot</dt>
                    <dd class="text-slate-700">{{ $session->is_robot ? 'Yes' : 'No' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Geo + referer --}}
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Location &amp; referer</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Country</dt>
                    <dd class="text-slate-700">{{ $session->country_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">City</dt>
                    <dd class="text-slate-700">{{ $session->city ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Referer</dt>
                    <dd class="truncate text-slate-700">{{ $session->referer_domain ?? 'direct' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Medium</dt>
                    <dd class="text-slate-700">{{ $session->referer_medium ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Timeline ({{ $timeline->count() }})</h2>
        <ul class="divide-y divide-slate-100">
            @forelse ($timeline as $entry)
                <li class="flex items-start gap-4 px-5 py-3 text-sm">
                    <span class="mt-0.5 rounded px-2 py-0.5 text-xs font-medium {{ $entry['type'] === 'event' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                        {{ $entry['type'] === 'event' ? 'event' : 'view' }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="truncate text-slate-900">{{ $entry['label'] }}</p>
                        @if ($entry['meta'])
                            <p class="truncate font-mono text-xs text-slate-500">{{ $entry['meta'] }}</p>
                        @endif
                    </div>
                    <time class="text-xs text-slate-500">{{ $entry['at']->format('H:i:s') }}</time>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-slate-500 text-sm">No activity.</li>
            @endforelse
        </ul>
    </div>
@endsection
```

- [ ] **Step 4: Add the route**

Edit `routes/dashboard.php` — add after sessions.index:

```php
Route::get('/sessions/{uuid}', [SessionsController::class, 'show'])->name('sessions.show');
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/pest tests/Feature/Dashboard/SessionDetailPageTest.php
```

Expected: 2 tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Http/Controllers/Dashboard/SessionsController.php \
        resources/views/pages/sessions/show.blade.php \
        routes/dashboard.php \
        tests/Feature/Dashboard/SessionDetailPageTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add session detail page with timeline"
```

---

## Task 5: Page views listing page

**Files:**
- Create: `src/Http/Controllers/Dashboard/PageViewsController.php`
- Create: `resources/views/pages/page-views.blade.php`
- Create: `tests/Feature/Dashboard/PageViewsPageTest.php`
- Modify: `routes/dashboard.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    Gate::define('viewTracker', fn ($user = null) => true);
});

it('renders the page views listing', function () {
    $session = Session::create([
        'uuid' => 'pv-list-1', 'visitor_uuid' => 'v-1', 'client_ip' => '1.1.1.1',
        'user_agent' => 'UA', 'device_kind' => 'desktop', 'device_platform' => 'macOS',
        'browser' => 'Chrome', 'browser_version' => '120',
        'language' => 'en', 'language_range' => 'en-US',
        'started_at' => now(), 'last_activity_at' => now(),
    ]);

    PageView::create([
        'session_id' => $session->id, 'method' => 'GET', 'path' => '/some-unique-path',
        'route_name' => 'some.route', 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'status_code' => 200, 'duration_ms' => 15, 'created_at' => now(),
    ]);

    $this->get('/tracker/page-views')
        ->assertOk()
        ->assertSee('Page views')
        ->assertSee('/some-unique-path')
        ->assertSee('some.route');
});

it('filters by path substring', function () {
    $session = Session::create([
        'uuid' => 'pv-list-2', 'visitor_uuid' => 'v-2', 'client_ip' => '2.2.2.2',
        'user_agent' => 'UA', 'device_kind' => 'desktop', 'device_platform' => 'macOS',
        'browser' => 'Chrome', 'browser_version' => '120',
        'language' => 'en', 'language_range' => 'en-US',
        'started_at' => now(), 'last_activity_at' => now(),
    ]);

    PageView::create([
        'session_id' => $session->id, 'method' => 'GET', 'path' => '/admin/users',
        'route_name' => null, 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'status_code' => 200, 'duration_ms' => 15, 'created_at' => now(),
    ]);
    PageView::create([
        'session_id' => $session->id, 'method' => 'GET', 'path' => '/home',
        'route_name' => null, 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'status_code' => 200, 'duration_ms' => 15, 'created_at' => now(),
    ]);

    $this->get('/tracker/page-views?path=admin')
        ->assertOk()
        ->assertSee('/admin/users')
        ->assertDontSee('/home');
});
```

- [ ] **Step 2: Write the controller**

Create `src/Http/Controllers/Dashboard/PageViewsController.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use OzanKurt\Tracker\Models\PageView;

class PageViewsController
{
    public function __invoke(Request $request): View
    {
        $query = PageView::query()->with('session')->orderByDesc('created_at');

        if ($path = $request->query('path')) {
            $query->where('path', 'like', '%'.$path.'%');
        }

        if ($route = $request->query('route')) {
            $query->where('route_name', (string) $route);
        }

        $pageViews = $query->paginate(50)->withQueryString();

        return view('tracker::pages.page-views', [
            'pageViews' => $pageViews,
            'filters'   => [
                'path'  => $request->query('path'),
                'route' => $request->query('route'),
            ],
        ]);
    }
}
```

- [ ] **Step 3: Write the view**

Create `resources/views/pages/page-views.blade.php`:

```blade
@extends('tracker::layout')

@section('title', 'Page views')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Page views</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $pageViews->total() }} total</p>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <input type="text" name="path" placeholder="Path contains..." value="{{ $filters['path'] }}"
               class="flex-1 min-w-[16rem] rounded-md border-slate-300 text-sm">
        <input type="text" name="route" placeholder="Exact route name" value="{{ $filters['route'] }}"
               class="rounded-md border-slate-300 text-sm">
        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
            Filter
        </button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Method</th>
                    <th class="px-4 py-3">Path</th>
                    <th class="px-4 py-3">Route</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Session</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($pageViews as $view)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $view->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $view->method }}</td>
                        <td class="px-4 py-3 text-slate-900 truncate max-w-md">{{ $view->path }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $view->route_name ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-700">{{ $view->status_code ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($view->session)
                                <a href="{{ route('tracker.sessions.show', $view->session->uuid) }}" class="font-mono text-xs text-indigo-600 hover:text-indigo-800">
                                    {{ Str::limit($view->session->uuid, 12, '…') }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No page views.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $pageViews->links() }}</div>
@endsection
```

- [ ] **Step 4: Add the route**

Edit `routes/dashboard.php` — add:

```php
use OzanKurt\Tracker\Http\Controllers\Dashboard\PageViewsController;

Route::get('/page-views', PageViewsController::class)->name('page-views');
```

- [ ] **Step 5: Run tests + commit**

```bash
./vendor/bin/pest tests/Feature/Dashboard/PageViewsPageTest.php
git add src/Http/Controllers/Dashboard/PageViewsController.php \
        resources/views/pages/page-views.blade.php \
        routes/dashboard.php \
        tests/Feature/Dashboard/PageViewsPageTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add dashboard page views listing"
```

---

## Task 6: Events listing page

**Files:**
- Create: `src/Http/Controllers/Dashboard/EventsController.php`
- Create: `resources/views/pages/events.blade.php`
- Create: `tests/Feature/Dashboard/EventsPageTest.php`
- Modify: `routes/dashboard.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    Gate::define('viewTracker', fn ($user = null) => true);
});

it('renders the events listing and filters by name', function () {
    $session = Session::create([
        'uuid' => 'ev-1', 'visitor_uuid' => 'v', 'client_ip' => '1.1.1.1',
        'user_agent' => 'UA', 'device_kind' => 'desktop', 'device_platform' => 'macOS',
        'browser' => 'Chrome', 'browser_version' => '120',
        'language' => 'en', 'language_range' => 'en-US',
        'started_at' => now(), 'last_activity_at' => now(),
    ]);

    Event::create(['session_id' => $session->id, 'name' => 'signup.completed', 'payload' => ['plan' => 'pro'], 'created_at' => now()]);
    Event::create(['session_id' => $session->id, 'name' => 'feature.used', 'payload' => [], 'created_at' => now()]);

    $this->get('/tracker/events')
        ->assertOk()
        ->assertSee('signup.completed')
        ->assertSee('feature.used');

    $this->get('/tracker/events?name=signup.completed')
        ->assertOk()
        ->assertSee('signup.completed')
        ->assertDontSee('feature.used');
});
```

- [ ] **Step 2: Write the controller**

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use OzanKurt\Tracker\Models\Event;

class EventsController
{
    public function __invoke(Request $request): View
    {
        $query = Event::query()->with('session')->orderByDesc('created_at');

        if ($name = $request->query('name')) {
            $query->where('name', (string) $name);
        }

        $events = $query->paginate(50)->withQueryString();

        return view('tracker::pages.events', [
            'events' => $events,
            'filter' => $request->query('name'),
        ]);
    }
}
```

- [ ] **Step 3: Write the view**

```blade
@extends('tracker::layout')

@section('title', 'Events')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Events</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $events->total() }} total</p>
    </div>

    <form method="GET" class="mb-4 flex gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <input type="text" name="name" placeholder="Event name (exact)" value="{{ $filter }}"
               class="flex-1 rounded-md border-slate-300 text-sm">
        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
            Filter
        </button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Payload</th>
                    <th class="px-4 py-3">Session</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($events as $event)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $event->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $event->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 truncate max-w-md">
                            {{ $event->payload ? json_encode($event->payload) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($event->session)
                                <a href="{{ route('tracker.sessions.show', $event->session->uuid) }}" class="font-mono text-xs text-indigo-600 hover:text-indigo-800">
                                    {{ Str::limit($event->session->uuid, 12, '…') }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No events.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $events->links() }}</div>
@endsection
```

- [ ] **Step 4: Add the route**

Edit `routes/dashboard.php`:

```php
use OzanKurt\Tracker\Http\Controllers\Dashboard\EventsController;

Route::get('/events', EventsController::class)->name('events');
```

- [ ] **Step 5: Run and commit**

```bash
./vendor/bin/pest tests/Feature/Dashboard/EventsPageTest.php
git add src/Http/Controllers/Dashboard/EventsController.php \
        resources/views/pages/events.blade.php \
        routes/dashboard.php \
        tests/Feature/Dashboard/EventsPageTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add dashboard events listing"
```

---

## Task 7: Per-user sessions page

Clicking through from a session's `user_id` should show all sessions for that user.

**Files:**
- Create: `src/Http/Controllers/Dashboard/UsersController.php`
- Create: `resources/views/pages/users/show.blade.php`
- Create: `tests/Feature/Dashboard/UserSessionsPageTest.php`
- Modify: `routes/dashboard.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    Gate::define('viewTracker', fn ($user = null) => true);
});

it('renders all sessions for a given user id', function () {
    Session::create([
        'uuid' => 'user-42-a', 'visitor_uuid' => 'v', 'user_id' => 42,
        'client_ip' => '1.1.1.1', 'user_agent' => 'UA',
        'device_kind' => 'desktop', 'device_platform' => 'macOS',
        'browser' => 'Chrome', 'browser_version' => '120',
        'language' => 'en', 'language_range' => 'en-US',
        'started_at' => now(), 'last_activity_at' => now(),
    ]);
    Session::create([
        'uuid' => 'user-42-b', 'visitor_uuid' => 'v', 'user_id' => 42,
        'client_ip' => '2.2.2.2', 'user_agent' => 'UA',
        'device_kind' => 'mobile', 'device_platform' => 'iOS',
        'browser' => 'Safari', 'browser_version' => '17',
        'language' => 'tr', 'language_range' => 'tr-TR',
        'started_at' => now(), 'last_activity_at' => now(),
    ]);
    Session::create([
        'uuid' => 'user-99', 'visitor_uuid' => 'v', 'user_id' => 99,
        'client_ip' => '3.3.3.3', 'user_agent' => 'UA',
        'device_kind' => 'desktop', 'device_platform' => 'Linux',
        'browser' => 'Firefox', 'browser_version' => '125',
        'language' => 'en', 'language_range' => 'en-US',
        'started_at' => now(), 'last_activity_at' => now(),
    ]);

    $this->get('/tracker/users/42')
        ->assertOk()
        ->assertSee('42')
        ->assertSee('user-42-a')
        ->assertSee('user-42-b')
        ->assertDontSee('user-99');
});
```

- [ ] **Step 2: Write the controller**

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use OzanKurt\Tracker\Models\Session;

class UsersController
{
    public function show(int|string $id): View
    {
        $sessions = Session::where('user_id', $id)
            ->orderByDesc('last_activity_at')
            ->paginate(25);

        return view('tracker::pages.users.show', [
            'userId'   => $id,
            'sessions' => $sessions,
        ]);
    }
}
```

- [ ] **Step 3: Write the view**

```blade
@extends('tracker::layout')

@section('title', 'User '.$userId)

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">User #{{ $userId }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $sessions->total() }} session(s)</p>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Session</th>
                    <th class="px-4 py-3">Started</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3">Device</th>
                    <th class="px-4 py-3">Browser</th>
                    <th class="px-4 py-3">Views</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sessions as $session)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('tracker.sessions.show', $session->uuid) }}" class="font-mono text-xs text-indigo-600 hover:text-indigo-800">
                                {{ $session->uuid }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $session->started_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $session->client_ip }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $session->device_kind }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $session->browser }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-900">{{ $session->page_views_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No sessions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $sessions->links() }}</div>
@endsection
```

- [ ] **Step 4: Add the route**

```php
use OzanKurt\Tracker\Http\Controllers\Dashboard\UsersController;

Route::get('/users/{id}', [UsersController::class, 'show'])->name('users.show');
```

- [ ] **Step 5: Run and commit**

```bash
./vendor/bin/pest tests/Feature/Dashboard/UserSessionsPageTest.php
git add src/Http/Controllers/Dashboard/UsersController.php \
        resources/views/pages/users/show.blade.php \
        routes/dashboard.php \
        tests/Feature/Dashboard/UserSessionsPageTest.php
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "feat(tracker): add dashboard per-user sessions page"
```

---

## Task 8: Green check

Final verification across the whole branch.

- [ ] **Step 1: Full suite**

```bash
./vendor/bin/pest
./vendor/bin/phpstan analyse --no-progress
./vendor/bin/pint --test
```

Expected: 71 (C-1) + 12 (C-2: 2 gate + 1 overview + 3 sessions list + 2 session detail + 2 page views + 1 events + 1 users) ≈ 83 tests passing. PHPStan clean. Pint clean.

Common phpstan concerns in the dashboard:
- `config('tracker.dashboard.middleware')` returns `mixed` → cast explicitly with `(array)`
- Controllers returning `View` → add explicit return type
- `$session->pageViews()->delete()` → Larastan may flag delete on HasMany; alternative `$session->pageViews->each->delete()` if it complains
- Blade partial prop narrowing — add `@props` declarations where used

Fix inline. If any test fails:
- Verify the route name matches (`tracker.sessions.index`, etc. — the group adds the `tracker.` prefix)
- Verify the view is discoverable (`tracker::pages.overview` resolves via `loadViewsFrom` with namespace `tracker`)
- Verify migration cascade doesn't break because the test creates a session before querying

- [ ] **Step 2: Commit any fixups**

```bash
git add -u
git -c user.name="Ozan Kurt" -c user.email="kurtozan@gmail.com" commit -m "chore(tracker): finalize dashboard UI — pint + phpstan clean"
```

---

## Definition of Done

- `/tracker` renders an overview with stat cards, chart, and top-N lists
- `/tracker/sessions` lists recent sessions with filters + pagination
- `/tracker/sessions/{uuid}` shows session detail with chronological timeline
- `/tracker/page-views` lists recent page views with path/route filters
- `/tracker/events` lists recent events filterable by name
- `/tracker/users/{id}` lists all sessions for a given app user id
- All routes are protected by `Authorize` middleware (Gate-based)
- Route registration is gated on `tracker.dashboard.enabled`
- Full suite green: pest, phpstan, pint
- All tasks committed on `feat/dashboard-ui`

After this, the package is v1-ready: tracking engine + admin panel + CI + docs.
