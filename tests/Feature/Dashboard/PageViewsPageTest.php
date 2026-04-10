<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
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
