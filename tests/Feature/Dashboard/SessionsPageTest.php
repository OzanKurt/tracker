<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
    Gate::define('viewTracker', fn ($user = null) => true);
});

function seedListSession(array $overrides = []): Session
{
    return Session::create(array_merge([
        'uuid' => 'list-'.uniqid(),
        'visitor_uuid' => 'v-'.uniqid(),
        'client_ip' => '203.0.113.1',
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
