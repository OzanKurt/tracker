<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

it('persists page views with json casts and belongs to a session', function () {
    $session = Session::create([
        'uuid' => 'session-uuid-pv',
        'visitor_uuid' => 'visitor-uuid-pv',
        'client_ip' => '203.0.113.1',
        'user_agent' => 'Mozilla/5.0',
        'device_kind' => 'desktop',
        'device_platform' => 'Windows',
        'browser' => 'Firefox',
        'browser_version' => '125.0',
        'language' => 'en-US',
        'language_range' => 'en-US,en;q=0.9',
        'started_at' => now(),
        'last_activity_at' => now(),
    ]);

    $view = PageView::create([
        'session_id' => $session->id,
        'method' => 'GET',
        'path' => '/dashboard',
        'route_name' => 'dashboard',
        'route_params' => ['tab' => 'overview'],
        'query_params' => ['ref' => 'nav'],
        'status_code' => 200,
        'duration_ms' => 42,
        'created_at' => now(),
    ]);

    $fresh = PageView::find($view->id);

    expect($fresh->route_params)->toBe(['tab' => 'overview'])
        ->and($fresh->query_params)->toBe(['ref' => 'nav'])
        ->and($fresh->session)->toBeInstanceOf(Session::class)
        ->and($fresh->session->id)->toBe($session->id);
});
