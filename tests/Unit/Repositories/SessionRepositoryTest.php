<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Repositories\SessionRepository;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations'));

it('creates a session from attributes', function () {
    $repo = new SessionRepository;

    $session = $repo->create([
        'uuid' => 'sess-1',
        'visitor_uuid' => 'vis-1',
        'client_ip' => '203.0.113.0',
        'user_agent' => 'UA',
        'device_kind' => 'desktop',
        'device_platform' => 'macOS',
        'browser' => 'Chrome',
        'browser_version' => '120',
        'language' => 'en',
        'language_range' => 'en-US,en;q=0.9',
        'started_at' => now(),
        'last_activity_at' => now(),
    ]);

    expect($session)->toBeInstanceOf(Session::class)
        ->and(Session::count())->toBe(1);
});

it('finds or creates a session by uuid (idempotent)', function () {
    $repo = new SessionRepository;

    $attrs = [
        'uuid' => 'sess-dup',
        'visitor_uuid' => 'vis-dup',
        'client_ip' => '203.0.113.1',
        'user_agent' => 'UA',
        'device_kind' => 'desktop',
        'device_platform' => 'Linux',
        'browser' => 'Firefox',
        'browser_version' => '125',
        'language' => 'tr',
        'language_range' => 'tr-TR',
        'started_at' => now(),
        'last_activity_at' => now(),
    ];

    $first = $repo->findOrCreateByUuid('sess-dup', $attrs);
    $second = $repo->findOrCreateByUuid('sess-dup', $attrs);

    expect($first->id)->toBe($second->id)
        ->and(Session::count())->toBe(1);
});

it('touches last_activity_at and increments page_views_count', function () {
    $repo = new SessionRepository;

    $session = $repo->create([
        'uuid' => 'sess-touch',
        'visitor_uuid' => 'vis-touch',
        'client_ip' => '203.0.113.2',
        'user_agent' => 'UA',
        'device_kind' => 'desktop',
        'device_platform' => 'Windows',
        'browser' => 'Edge',
        'browser_version' => '120',
        'language' => 'en',
        'language_range' => 'en-US',
        'started_at' => now()->subMinutes(5),
        'last_activity_at' => now()->subMinutes(5),
    ]);

    $repo->touchActivity($session, pageViewDelta: 1, eventDelta: 0);
    $session->refresh();

    expect($session->page_views_count)->toBe(1)
        ->and($session->events_count)->toBe(0)
        ->and($session->last_activity_at->diffInMinutes(now()))->toBeLessThan(1);
});
