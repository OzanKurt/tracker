<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    config()->set('tracker.privacy.retention_days', 30);
});

function seedOldSession(): Session
{
    return Session::create([
        'uuid' => 'old-'.uniqid(),
        'visitor_uuid' => 'v-'.uniqid(),
        'client_ip' => '203.0.113.1',
        'user_agent' => 'UA',
        'device_kind' => 'desktop',
        'device_platform' => 'macOS',
        'browser' => 'Chrome',
        'browser_version' => '120',
        'language' => 'en',
        'language_range' => 'en-US',
        'started_at' => now()->subDays(45),
        'last_activity_at' => now()->subDays(45),
    ]);
}

function seedFreshSession(): Session
{
    return Session::create([
        'uuid' => 'fresh-'.uniqid(),
        'visitor_uuid' => 'v-'.uniqid(),
        'client_ip' => '203.0.113.2',
        'user_agent' => 'UA',
        'device_kind' => 'desktop',
        'device_platform' => 'macOS',
        'browser' => 'Chrome',
        'browser_version' => '120',
        'language' => 'en',
        'language_range' => 'en-US',
        'started_at' => now()->subDays(3),
        'last_activity_at' => now()->subDays(3),
    ]);
}

it('prunes sessions older than retention_days', function () {
    $old = seedOldSession();
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
        ->and(PageView::count())->toBe(0)
        ->and(Event::count())->toBe(0);
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
