<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Stats\TrackerStats;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations'));

function seedSession(array $overrides = []): Session
{
    return Session::create(array_merge([
        'uuid' => 'sess-'.uniqid(),
        'visitor_uuid' => 'vis-'.uniqid(),
        'client_ip' => '203.0.113.'.random_int(1, 254),
        'user_agent' => 'UA',
        'device_kind' => 'desktop',
        'device_platform' => 'macOS',
        'browser' => 'Chrome',
        'browser_version' => '120',
        'language' => 'en',
        'language_range' => 'en-US',
        'country_code' => 'TR',
        'country_name' => 'Türkiye',
        'started_at' => now(),
        'last_activity_at' => now(),
    ], $overrides));
}

function seedPageView(Session $session, string $path, ?string $routeName = null): PageView
{
    return PageView::create([
        'session_id' => $session->id,
        'method' => 'GET',
        'path' => $path,
        'route_name' => $routeName,
        'route_action' => null,
        'route_params' => [],
        'query_params' => [],
        'status_code' => 200,
        'duration_ms' => 10,
        'created_at' => now(),
    ]);
}

it('counts unique visitors within a window', function () {
    seedSession(['visitor_uuid' => 'v1']);
    seedSession(['visitor_uuid' => 'v2']);
    seedSession(['visitor_uuid' => 'v1', 'uuid' => 'sess-v1-again']);

    $stats = new TrackerStats;
    $count = $stats->uniqueVisitors(Carbon::now()->subHour());

    expect($count)->toBe(2);
});

it('returns top pages by view count', function () {
    $session = seedSession();
    seedPageView($session, '/home', 'home');
    seedPageView($session, '/home', 'home');
    seedPageView($session, '/about', 'about');

    $top = (new TrackerStats)->topPages(Carbon::now()->subHour(), 10);

    expect($top)->toHaveCount(2);
    $first = $top->first();
    expect($first->path)->toBe('/home')
        ->and((int) $first->views)->toBe(2);
});

it('returns top countries by session count', function () {
    seedSession(['country_code' => 'TR', 'country_name' => 'Türkiye']);
    seedSession(['country_code' => 'TR', 'country_name' => 'Türkiye']);
    seedSession(['country_code' => 'US', 'country_name' => 'United States']);

    $top = (new TrackerStats)->topCountries(Carbon::now()->subHour(), 10);

    expect($top)->toHaveCount(2);
    expect($top->first()->country_code)->toBe('TR')
        ->and((int) $top->first()->sessions)->toBe(2);
});

it('returns top browsers by session count', function () {
    seedSession(['browser' => 'Chrome']);
    seedSession(['browser' => 'Chrome']);
    seedSession(['browser' => 'Firefox']);

    $top = (new TrackerStats)->topBrowsers(Carbon::now()->subHour(), 10);

    expect($top)->toHaveCount(2);
    expect($top->first()->browser)->toBe('Chrome')
        ->and((int) $top->first()->sessions)->toBe(2);
});

it('buckets sessions over time by hour', function () {
    Carbon::setTestNow('2026-04-10 14:30:00');
    seedSession(['started_at' => '2026-04-10 13:10:00', 'last_activity_at' => '2026-04-10 13:10:00']);
    seedSession(['started_at' => '2026-04-10 13:45:00', 'last_activity_at' => '2026-04-10 13:45:00']);
    seedSession(['started_at' => '2026-04-10 14:05:00', 'last_activity_at' => '2026-04-10 14:05:00']);

    $buckets = (new TrackerStats)->sessionsOverTime(Carbon::parse('2026-04-10 12:00:00'), 'hour');

    expect($buckets)->not->toBeEmpty();
    Carbon::setTestNow();
});
