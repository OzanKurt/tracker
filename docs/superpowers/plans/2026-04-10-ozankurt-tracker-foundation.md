# ozankurt/tracker — Plan A: Foundation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Scaffold the `ozankurt/tracker` Laravel package shell — composer config, service provider, config file, four migrations, four Eloquent models, and a working testbench test harness. End state: `composer install` works, `php artisan migrate` runs all four migrations clean, all model unit tests pass.

**Architecture:** Standard Laravel package layout following Telescope/Horizon conventions. Package lives under `packages/tracker/` in this monorepo, with namespace `OzanKurt\Tracker\`. Uses `orchestra/testbench` for Laravel package testing and Pest 3 for test framework. This plan produces no runtime tracking behavior — only the skeleton, schema, and models.

**Tech Stack:** PHP 8.3, Laravel 12, Pest 3, orchestra/testbench 10, Larastan 3, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-04-10-ozankurt-tracker-design.md`

---

## File Structure

Files created in this plan:

```
packages/tracker/
├── composer.json                                             # package manifest
├── phpunit.xml                                               # Pest config
├── pint.json                                                 # code style
├── phpstan.neon                                              # Larastan config
├── .gitignore
├── README.md                                                 # minimal stub
├── config/tracker.php                                        # package config
├── database/migrations/
│   ├── 2026_04_10_000001_create_tracker_sessions_table.php
│   ├── 2026_04_10_000002_create_tracker_page_views_table.php
│   ├── 2026_04_10_000003_create_tracker_events_table.php
│   └── 2026_04_10_000004_create_tracker_geoip_cache_table.php
├── src/
│   ├── TrackerServiceProvider.php                            # registers config, migrations
│   ├── Facades/Tracker.php                                   # stub facade
│   ├── Tracker.php                                           # stub service (empty)
│   └── Models/
│       ├── Session.php
│       ├── PageView.php
│       ├── Event.php
│       └── GeoIpCache.php
└── tests/
    ├── Pest.php
    ├── TestCase.php                                          # testbench base
    └── Unit/
        ├── MigrationsTest.php
        └── Models/
            ├── SessionTest.php
            ├── PageViewTest.php
            ├── EventTest.php
            └── GeoIpCacheTest.php
```

Rationale:
- **One file per model** keeps each model file focused and small.
- **Migrations numbered 2026_04_10_00000N** so ordering is stable and obvious.
- **Stub `Tracker.php` service** is created now so the facade has a binding target; real methods come in Plan B.
- **`MigrationsTest`** is a sanity check — it runs all four migrations in sequence against SQLite and asserts the schema shape. This catches column name / type mistakes early.

---

## Task 1: Create package directory and composer.json

**Files:**
- Create: `packages/tracker/composer.json`
- Create: `packages/tracker/.gitignore`
- Create: `packages/tracker/README.md`

- [ ] **Step 1: Create the package directory structure**

Run:
```bash
mkdir -p packages/tracker/src/Models packages/tracker/src/Facades \
         packages/tracker/config packages/tracker/database/migrations \
         packages/tracker/tests/Unit/Models
```

- [ ] **Step 2: Write composer.json**

Create `packages/tracker/composer.json`:

```json
{
    "name": "ozankurt/tracker",
    "description": "A modern, privacy-first Laravel visitor analytics package.",
    "keywords": ["laravel", "tracker", "analytics", "visitor", "privacy", "geoip"],
    "license": "MIT",
    "authors": [
        { "name": "Ozan Kurt", "email": "kurtozan@gmail.com" }
    ],
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "ozankurt/agent": "^1.0"
    },
    "require-dev": {
        "orchestra/testbench": "^10.0",
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0",
        "larastan/larastan": "^3.0",
        "laravel/pint": "^1.18"
    },
    "suggest": {
        "geoip2/geoip2": "Enables the MaxMind GeoLite2 geo-IP driver"
    },
    "autoload": {
        "psr-4": {
            "OzanKurt\\Tracker\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "OzanKurt\\Tracker\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "OzanKurt\\Tracker\\TrackerServiceProvider"
            ],
            "aliases": {
                "Tracker": "OzanKurt\\Tracker\\Facades\\Tracker"
            }
        }
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- [ ] **Step 3: Write .gitignore**

Create `packages/tracker/.gitignore`:

```
/vendor/
/node_modules/
/.phpunit.cache/
/.phpunit.result.cache
composer.lock
.phpunit.result.cache
```

- [ ] **Step 4: Write README stub**

Create `packages/tracker/README.md`:

```markdown
# ozankurt/tracker

Modern, privacy-first Laravel visitor analytics. Successor to `pragmarx/tracker`.

**Status:** In development. See `docs/superpowers/specs/` for the design spec.

## Requirements
- PHP 8.3+
- Laravel 12+

## Install
Coming soon.
```

- [ ] **Step 5: Install composer dependencies**

Run:
```bash
cd packages/tracker && composer install
```

Expected: `composer.lock` is created, `vendor/` directory exists, no errors. If `ozankurt/agent` cannot be resolved, temporarily remove it from `require` and add a TODO comment — it will be re-added in Plan B when the enricher actually needs it. Note the removal in your commit message.

- [ ] **Step 6: Commit**

```bash
git add packages/tracker/composer.json packages/tracker/.gitignore \
        packages/tracker/README.md packages/tracker/composer.lock
git commit -m "feat(tracker): scaffold package with composer manifest"
```

---

## Task 2: Add tooling config (phpunit, pest, pint, larastan)

**Files:**
- Create: `packages/tracker/phpunit.xml`
- Create: `packages/tracker/pint.json`
- Create: `packages/tracker/phpstan.neon`

- [ ] **Step 1: Write phpunit.xml**

Create `packages/tracker/phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         processIsolation="false"
         stopOnFailure="false"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
    <php>
        <env name="DB_CONNECTION" value="testing"/>
        <env name="APP_ENV" value="testing"/>
    </php>
</phpunit>
```

- [ ] **Step 2: Write pint.json**

Create `packages/tracker/pint.json`:

```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "strict_comparison": true,
        "strict_param": true
    }
}
```

- [ ] **Step 3: Write phpstan.neon**

Create `packages/tracker/phpstan.neon`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 8
    paths:
        - src
```

- [ ] **Step 4: Verify tools are runnable**

Run from `packages/tracker/`:
```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
./vendor/bin/pest --version
```

Expected: all three commands run without crashing. Pint and PHPStan may report no files yet (fine). Pest prints its version.

- [ ] **Step 5: Commit**

```bash
git add packages/tracker/phpunit.xml packages/tracker/pint.json \
        packages/tracker/phpstan.neon
git commit -m "chore(tracker): add phpunit, pint, and phpstan config"
```

---

## Task 3: Write the testbench TestCase and Pest bootstrap

**Files:**
- Create: `packages/tracker/tests/TestCase.php`
- Create: `packages/tracker/tests/Pest.php`

- [ ] **Step 1: Write TestCase.php**

Create `packages/tracker/tests/TestCase.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Tests;

use OzanKurt\Tracker\TrackerServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [TrackerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
```

- [ ] **Step 2: Write Pest.php bootstrap**

Create `packages/tracker/tests/Pest.php`:

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature');
```

- [ ] **Step 3: Commit**

```bash
git add packages/tracker/tests/TestCase.php packages/tracker/tests/Pest.php
git commit -m "test(tracker): add testbench TestCase and Pest bootstrap"
```

Note: we cannot run pest yet because `TrackerServiceProvider` does not exist — that comes in Task 5.

---

## Task 4: Write the config file

**Files:**
- Create: `packages/tracker/config/tracker.php`

- [ ] **Step 1: Write config/tracker.php**

Create `packages/tracker/config/tracker.php`:

```php
<?php

declare(strict_types=1);

return [
    'enabled' => env('TRACKER_ENABLED', true),

    'dispatcher' => env('TRACKER_DISPATCHER', 'queue'), // queue | sync | defer

    'queue' => [
        'connection' => env('TRACKER_QUEUE_CONNECTION'),
        'name'       => env('TRACKER_QUEUE_NAME', 'default'),
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
        'anonymize_ip'   => true,
        'respect_dnt'    => true,
        'retention_days' => 90, // 0 = forever
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
        'middleware' => ['web'],
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add packages/tracker/config/tracker.php
git commit -m "feat(tracker): add package config file"
```

---

## Task 5: Write the TrackerServiceProvider skeleton

**Files:**
- Create: `packages/tracker/src/TrackerServiceProvider.php`
- Create: `packages/tracker/src/Tracker.php`
- Create: `packages/tracker/src/Facades/Tracker.php`

- [ ] **Step 1: Write the stub Tracker service**

Create `packages/tracker/src/Tracker.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker;

class Tracker
{
    // Stub. Real methods land in Plan B (Tracking Engine).
}
```

- [ ] **Step 2: Write the Facade**

Create `packages/tracker/src/Facades/Tracker.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Facades;

use Illuminate\Support\Facades\Facade;

/**
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

- [ ] **Step 3: Write the ServiceProvider**

Create `packages/tracker/src/TrackerServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker;

use Illuminate\Support\ServiceProvider;

class TrackerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/tracker.php',
            'tracker'
        );

        $this->app->singleton(Tracker::class, fn ($app) => new Tracker());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/tracker.php' => config_path('tracker.php'),
            ], 'tracker-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'tracker-migrations');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
```

- [ ] **Step 4: Write a smoke test for the service provider**

Create `packages/tracker/tests/Unit/ServiceProviderTest.php`:

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Tracker;

it('binds the Tracker service as a singleton', function () {
    $first = app(Tracker::class);
    $second = app(Tracker::class);

    expect($first)->toBeInstanceOf(Tracker::class)
        ->and($first)->toBe($second);
});

it('loads the tracker config', function () {
    expect(config('tracker.enabled'))->toBeTrue()
        ->and(config('tracker.dispatcher'))->toBe('queue')
        ->and(config('tracker.privacy.anonymize_ip'))->toBeTrue();
});
```

- [ ] **Step 5: Run the test**

Run from `packages/tracker/`:
```bash
./vendor/bin/pest tests/Unit/ServiceProviderTest.php
```

Expected: both tests PASS. If they fail, fix before committing.

- [ ] **Step 6: Commit**

```bash
git add packages/tracker/src/TrackerServiceProvider.php \
        packages/tracker/src/Tracker.php \
        packages/tracker/src/Facades/Tracker.php \
        packages/tracker/tests/Unit/ServiceProviderTest.php
git commit -m "feat(tracker): add service provider, facade, and stub service"
```

---

## Task 6: Migration — tracker_sessions

**Files:**
- Create: `packages/tracker/database/migrations/2026_04_10_000001_create_tracker_sessions_table.php`

- [ ] **Step 1: Write the migration**

Create the file:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->char('visitor_uuid', 36)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('client_ip', 45)->index();
            $table->text('user_agent');

            $table->string('device_kind', 32);
            $table->string('device_model', 64)->nullable();
            $table->string('device_platform', 32);
            $table->string('device_platform_ver', 32)->nullable();
            $table->string('browser', 64);
            $table->string('browser_version', 32);

            $table->string('language', 10);
            $table->string('language_range', 64);

            $table->boolean('is_robot')->default(false);

            $table->char('country_code', 2)->nullable()->index();
            $table->string('country_name', 128)->nullable();
            $table->string('city', 128)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();

            $table->text('referer_url')->nullable();
            $table->string('referer_domain', 255)->nullable()->index();
            $table->string('referer_medium', 32)->nullable();
            $table->string('referer_source', 64)->nullable();
            $table->string('referer_search_term', 255)->nullable();

            $table->timestamp('started_at')->index();
            $table->timestamp('last_activity_at')->index();
            $table->timestamp('ended_at')->nullable();

            $table->unsignedInteger('page_views_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_sessions');
    }
};
```

- [ ] **Step 2: Commit**

```bash
git add packages/tracker/database/migrations/2026_04_10_000001_create_tracker_sessions_table.php
git commit -m "feat(tracker): add tracker_sessions migration"
```

---

## Task 7: Migration — tracker_page_views

**Files:**
- Create: `packages/tracker/database/migrations/2026_04_10_000002_create_tracker_page_views_table.php`

- [ ] **Step 1: Write the migration**

Create the file:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('tracker_sessions')
                ->cascadeOnDelete();
            $table->string('method', 8);
            // Long path is not indexed directly — it can exceed MySQL's
            // 767-byte key limit on older row formats. Use route_name for
            // route-level aggregation, and a DB-specific functional index
            // later if path-level lookups become hot.
            $table->string('path', 2048);
            $table->string('route_name', 128)->nullable()->index();
            $table->string('route_action', 255)->nullable();
            $table->json('route_params')->nullable();
            $table->json('query_params')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_page_views');
    }
};
```

- [ ] **Step 2: Commit**

```bash
git add packages/tracker/database/migrations/2026_04_10_000002_create_tracker_page_views_table.php
git commit -m "feat(tracker): add tracker_page_views migration"
```

---

## Task 8: Migration — tracker_events

**Files:**
- Create: `packages/tracker/database/migrations/2026_04_10_000003_create_tracker_events_table.php`

- [ ] **Step 1: Write the migration**

Create the file:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('tracker_sessions')
                ->cascadeOnDelete();
            $table->string('name', 128)->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_events');
    }
};
```

- [ ] **Step 2: Commit**

```bash
git add packages/tracker/database/migrations/2026_04_10_000003_create_tracker_events_table.php
git commit -m "feat(tracker): add tracker_events migration"
```

---

## Task 9: Migration — tracker_geoip_cache

**Files:**
- Create: `packages/tracker/database/migrations/2026_04_10_000004_create_tracker_geoip_cache_table.php`

- [ ] **Step 1: Write the migration**

Create the file:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_geoip_cache', function (Blueprint $table) {
            $table->id();
            $table->char('ip_hash', 64)->unique();
            $table->char('country_code', 2)->nullable();
            $table->string('country_name', 128)->nullable();
            $table->string('city', 128)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('provider', 32);
            $table->timestamp('cached_until')->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_geoip_cache');
    }
};
```

- [ ] **Step 2: Commit**

```bash
git add packages/tracker/database/migrations/2026_04_10_000004_create_tracker_geoip_cache_table.php
git commit -m "feat(tracker): add tracker_geoip_cache migration"
```

---

## Task 10: Migration sanity test (all four run clean)

**Files:**
- Create: `packages/tracker/tests/Unit/MigrationsTest.php`

- [ ] **Step 1: Write the test**

Create `packages/tracker/tests/Unit/MigrationsTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

uses(\OzanKurt\Tracker\Tests\TestCase::class)
    ->beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations'));

it('creates tracker_sessions with expected columns', function () {
    expect(Schema::hasTable('tracker_sessions'))->toBeTrue();

    foreach ([
        'id', 'uuid', 'visitor_uuid', 'user_id', 'client_ip', 'user_agent',
        'device_kind', 'device_platform', 'browser', 'browser_version',
        'language', 'is_robot',
        'country_code', 'country_name', 'city', 'latitude', 'longitude',
        'referer_url', 'referer_domain', 'referer_medium',
        'started_at', 'last_activity_at', 'ended_at',
        'page_views_count', 'events_count',
        'created_at', 'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('tracker_sessions', $column))
            ->toBeTrue("Column {$column} missing from tracker_sessions");
    }
});

it('creates tracker_page_views with expected columns', function () {
    expect(Schema::hasTable('tracker_page_views'))->toBeTrue();
    foreach (['id', 'session_id', 'method', 'path', 'route_name',
              'route_params', 'query_params', 'status_code', 'created_at'] as $c) {
        expect(Schema::hasColumn('tracker_page_views', $c))->toBeTrue();
    }
});

it('creates tracker_events with expected columns', function () {
    expect(Schema::hasTable('tracker_events'))->toBeTrue();
    foreach (['id', 'session_id', 'name', 'payload', 'created_at'] as $c) {
        expect(Schema::hasColumn('tracker_events', $c))->toBeTrue();
    }
});

it('creates tracker_geoip_cache with expected columns', function () {
    expect(Schema::hasTable('tracker_geoip_cache'))->toBeTrue();
    foreach (['id', 'ip_hash', 'country_code', 'country_name',
              'city', 'latitude', 'longitude', 'provider',
              'cached_until', 'created_at'] as $c) {
        expect(Schema::hasColumn('tracker_geoip_cache', $c))->toBeTrue();
    }
});
```

- [ ] **Step 2: Run the test**

Run from `packages/tracker/`:
```bash
./vendor/bin/pest tests/Unit/MigrationsTest.php
```

Expected: all four tests PASS. If any column is missing, fix the corresponding migration.

- [ ] **Step 3: Commit**

```bash
git add packages/tracker/tests/Unit/MigrationsTest.php
git commit -m "test(tracker): verify all migrations produce expected schema"
```

---

## Task 11: Session Eloquent model

**Files:**
- Create: `packages/tracker/src/Models/Session.php`
- Create: `packages/tracker/tests/Unit/Models/SessionTest.php`

- [ ] **Step 1: Write the failing test**

Create `packages/tracker/tests/Unit/Models/SessionTest.php`:

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\Session;

it('can be persisted and hydrated with casts', function () {
    $session = Session::create([
        'uuid'                => 'session-uuid-1',
        'visitor_uuid'        => 'visitor-uuid-1',
        'client_ip'           => '203.0.113.0',
        'user_agent'          => 'Mozilla/5.0',
        'device_kind'         => 'desktop',
        'device_platform'     => 'macOS',
        'browser'             => 'Chrome',
        'browser_version'     => '120.0',
        'language'            => 'en-US',
        'language_range'      => 'en-US,en;q=0.9',
        'is_robot'            => false,
        'latitude'            => 37.7749,
        'longitude'           => -122.4194,
        'started_at'          => now(),
        'last_activity_at'    => now(),
    ]);

    $fresh = Session::find($session->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->is_robot)->toBeFalse()
        ->and((float) $fresh->latitude)->toBe(37.7749)
        ->and($fresh->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Models/SessionTest.php`
Expected: FAIL with "Class OzanKurt\Tracker\Models\Session not found".

- [ ] **Step 3: Write the Session model**

Create `packages/tracker/src/Models/Session.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    protected $table = 'tracker_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'is_robot'         => 'boolean',
        'latitude'         => 'float',
        'longitude'        => 'float',
        'started_at'       => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at'         => 'datetime',
        'page_views_count' => 'integer',
        'events_count'     => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'user_id');
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class, 'session_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'session_id');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Models/SessionTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/tracker/src/Models/Session.php \
        packages/tracker/tests/Unit/Models/SessionTest.php
git commit -m "feat(tracker): add Session model with casts and relations"
```

---

## Task 12: PageView Eloquent model

**Files:**
- Create: `packages/tracker/src/Models/PageView.php`
- Create: `packages/tracker/tests/Unit/Models/PageViewTest.php`

- [ ] **Step 1: Write the failing test**

Create `packages/tracker/tests/Unit/Models/PageViewTest.php`:

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

it('persists page views with json casts and belongs to a session', function () {
    $session = Session::create([
        'uuid'                => 'session-uuid-pv',
        'visitor_uuid'        => 'visitor-uuid-pv',
        'client_ip'           => '203.0.113.1',
        'user_agent'          => 'Mozilla/5.0',
        'device_kind'         => 'desktop',
        'device_platform'     => 'Windows',
        'browser'             => 'Firefox',
        'browser_version'     => '125.0',
        'language'            => 'en-US',
        'language_range'      => 'en-US,en;q=0.9',
        'started_at'          => now(),
        'last_activity_at'    => now(),
    ]);

    $view = PageView::create([
        'session_id'   => $session->id,
        'method'       => 'GET',
        'path'         => '/dashboard',
        'route_name'   => 'dashboard',
        'route_params' => ['tab' => 'overview'],
        'query_params' => ['ref' => 'nav'],
        'status_code'  => 200,
        'duration_ms'  => 42,
        'created_at'   => now(),
    ]);

    $fresh = PageView::find($view->id);

    expect($fresh->route_params)->toBe(['tab' => 'overview'])
        ->and($fresh->query_params)->toBe(['ref' => 'nav'])
        ->and($fresh->session)->toBeInstanceOf(Session::class)
        ->and($fresh->session->id)->toBe($session->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Models/PageViewTest.php`
Expected: FAIL with class-not-found error.

- [ ] **Step 3: Write the PageView model**

Create `packages/tracker/src/Models/PageView.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    public $timestamps = false;

    protected $table = 'tracker_page_views';

    protected $guarded = ['id'];

    protected $casts = [
        'route_params' => 'array',
        'query_params' => 'array',
        'status_code'  => 'integer',
        'duration_ms'  => 'integer',
        'created_at'   => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Models/PageViewTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/tracker/src/Models/PageView.php \
        packages/tracker/tests/Unit/Models/PageViewTest.php
git commit -m "feat(tracker): add PageView model with json casts"
```

---

## Task 13: Event Eloquent model

**Files:**
- Create: `packages/tracker/src/Models/Event.php`
- Create: `packages/tracker/tests/Unit/Models/EventTest.php`

- [ ] **Step 1: Write the failing test**

Create `packages/tracker/tests/Unit/Models/EventTest.php`:

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\Session;

it('persists events with json payload and belongs to a session', function () {
    $session = Session::create([
        'uuid'                => 'session-uuid-ev',
        'visitor_uuid'        => 'visitor-uuid-ev',
        'client_ip'           => '203.0.113.2',
        'user_agent'          => 'Mozilla/5.0',
        'device_kind'         => 'desktop',
        'device_platform'     => 'Linux',
        'browser'             => 'Chrome',
        'browser_version'     => '121.0',
        'language'            => 'tr-TR',
        'language_range'      => 'tr-TR,tr;q=0.9',
        'started_at'          => now(),
        'last_activity_at'    => now(),
    ]);

    $event = Event::create([
        'session_id' => $session->id,
        'name'       => 'signup.completed',
        'payload'    => ['plan' => 'pro', 'referrer' => 'blog'],
        'created_at' => now(),
    ]);

    $fresh = Event::find($event->id);

    expect($fresh->name)->toBe('signup.completed')
        ->and($fresh->payload)->toBe(['plan' => 'pro', 'referrer' => 'blog'])
        ->and($fresh->session->id)->toBe($session->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Models/EventTest.php`
Expected: FAIL with class-not-found error.

- [ ] **Step 3: Write the Event model**

Create `packages/tracker/src/Models/Event.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    public $timestamps = false;

    protected $table = 'tracker_events';

    protected $guarded = ['id'];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Models/EventTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/tracker/src/Models/Event.php \
        packages/tracker/tests/Unit/Models/EventTest.php
git commit -m "feat(tracker): add Event model with json payload cast"
```

---

## Task 14: GeoIpCache Eloquent model

**Files:**
- Create: `packages/tracker/src/Models/GeoIpCache.php`
- Create: `packages/tracker/tests/Unit/Models/GeoIpCacheTest.php`

- [ ] **Step 1: Write the failing test**

Create `packages/tracker/tests/Unit/Models/GeoIpCacheTest.php`:

```php
<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\GeoIpCache;

it('persists a geoip cache entry and exposes typed columns', function () {
    $entry = GeoIpCache::create([
        'ip_hash'      => str_repeat('a', 64),
        'country_code' => 'TR',
        'country_name' => 'Türkiye',
        'city'         => 'Istanbul',
        'latitude'     => 41.0082,
        'longitude'    => 28.9784,
        'provider'     => 'ipapi',
        'cached_until' => now()->addDays(30),
        'created_at'   => now(),
    ]);

    $fresh = GeoIpCache::find($entry->id);

    expect($fresh->country_code)->toBe('TR')
        ->and((float) $fresh->latitude)->toBe(41.0082)
        ->and($fresh->cached_until)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('enforces unique ip_hash', function () {
    $hash = str_repeat('b', 64);

    GeoIpCache::create([
        'ip_hash' => $hash,
        'provider' => 'null',
        'cached_until' => now()->addDay(),
        'created_at' => now(),
    ]);

    expect(fn () => GeoIpCache::create([
        'ip_hash' => $hash,
        'provider' => 'null',
        'cached_until' => now()->addDay(),
        'created_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Models/GeoIpCacheTest.php`
Expected: FAIL with class-not-found error.

- [ ] **Step 3: Write the GeoIpCache model**

Create `packages/tracker/src/Models/GeoIpCache.php`:

```php
<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Models;

use Illuminate\Database\Eloquent\Model;

class GeoIpCache extends Model
{
    public $timestamps = false;

    protected $table = 'tracker_geoip_cache';

    protected $guarded = ['id'];

    protected $casts = [
        'latitude'     => 'float',
        'longitude'    => 'float',
        'cached_until' => 'datetime',
        'created_at'   => 'datetime',
    ];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Models/GeoIpCacheTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/tracker/src/Models/GeoIpCache.php \
        packages/tracker/tests/Unit/Models/GeoIpCacheTest.php
git commit -m "feat(tracker): add GeoIpCache model"
```

---

## Task 15: Green check — full test suite and static analysis

**Files:**
- None (verification only)

- [ ] **Step 1: Run the full Pest suite**

Run from `packages/tracker/`:
```bash
./vendor/bin/pest
```

Expected: ALL tests PASS. Count should be ~10+ tests (service provider, migrations, four model tests). Fix anything red.

- [ ] **Step 2: Run Larastan**

Run from `packages/tracker/`:
```bash
./vendor/bin/phpstan analyse --no-progress
```

Expected: no errors at level 8. Known acceptable warning categories if they arise: generic Eloquent model inference (`::create()` signatures) — fix by adding phpdoc hints on the models rather than lowering the level.

- [ ] **Step 3: Run Pint in check mode**

Run from `packages/tracker/`:
```bash
./vendor/bin/pint --test
```

Expected: no style violations. If there are any, run `./vendor/bin/pint` to auto-fix, re-run the test suite, and include the fixes in a follow-up commit.

- [ ] **Step 4: Final commit (only if Pint or phpdoc fixes were needed)**

```bash
git add -u
git commit -m "chore(tracker): finalize foundation — pint + phpstan clean"
```

---

## Definition of Done

Plan A is complete when:

- `packages/tracker/` exists with composer.json, config, migrations, models, service provider, facade, and test harness
- `composer install` in `packages/tracker/` succeeds
- `./vendor/bin/pest` reports all green (service provider, migrations, four model tests)
- `./vendor/bin/phpstan analyse` reports level 8 clean
- `./vendor/bin/pint --test` reports no style issues
- Every task has been committed

After this, Plan B (Tracking Engine) can be written with confidence that the schema, models, and testbench harness are stable.
