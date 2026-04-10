<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
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
