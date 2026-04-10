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
