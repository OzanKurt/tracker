<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Repositories\PageViewRepository;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations'));

function makeSessionForPageViewRepoTest(): Session
{
    return Session::create([
        'uuid' => 'sess-pv-'.uniqid(),
        'visitor_uuid' => 'vis-pv-'.uniqid(),
        'client_ip' => '203.0.113.10',
        'user_agent' => 'UA',
        'device_kind' => 'desktop',
        'device_platform' => 'macOS',
        'browser' => 'Chrome',
        'browser_version' => '120',
        'language' => 'en',
        'language_range' => 'en-US',
        'started_at' => now(),
        'last_activity_at' => now(),
    ]);
}

it('creates a page view for a session', function () {
    $session = makeSessionForPageViewRepoTest();
    $repo = new PageViewRepository;

    $view = $repo->create([
        'session_id' => $session->id,
        'method' => 'GET',
        'path' => '/dashboard',
        'route_name' => 'dashboard',
        'route_action' => null,
        'route_params' => [],
        'query_params' => ['tab' => 'overview'],
        'status_code' => 200,
        'duration_ms' => 45,
        'created_at' => now(),
    ]);

    expect($view)->toBeInstanceOf(PageView::class)
        ->and($view->session_id)->toBe($session->id)
        ->and($view->query_params)->toBe(['tab' => 'overview']);
});
