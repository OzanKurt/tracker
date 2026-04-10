<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
    Gate::define('viewTracker', fn ($user = null) => true);
});

it('renders all sessions for a given user id', function () {
    Session::create([
        'uuid' => 'user-42-a', 'visitor_uuid' => 'v', 'user_id' => 42,
        'client_ip' => '1.1.1.1', 'user_agent' => 'UA',
        'device_kind' => 'desktop', 'device_platform' => 'macOS',
        'browser' => 'Chrome', 'browser_version' => '120',
        'language' => 'en', 'language_range' => 'en-US',
        'started_at' => now(), 'last_activity_at' => now(),
    ]);
    Session::create([
        'uuid' => 'user-42-b', 'visitor_uuid' => 'v', 'user_id' => 42,
        'client_ip' => '2.2.2.2', 'user_agent' => 'UA',
        'device_kind' => 'mobile', 'device_platform' => 'iOS',
        'browser' => 'Safari', 'browser_version' => '17',
        'language' => 'tr', 'language_range' => 'tr-TR',
        'started_at' => now(), 'last_activity_at' => now(),
    ]);
    Session::create([
        'uuid' => 'user-99', 'visitor_uuid' => 'v', 'user_id' => 99,
        'client_ip' => '3.3.3.3', 'user_agent' => 'UA',
        'device_kind' => 'desktop', 'device_platform' => 'Linux',
        'browser' => 'Firefox', 'browser_version' => '125',
        'language' => 'en', 'language_range' => 'en-US',
        'started_at' => now(), 'last_activity_at' => now(),
    ]);

    $this->get('/tracker/users/42')
        ->assertOk()
        ->assertSee('42')
        ->assertSee('user-42-a')
        ->assertSee('user-42-b')
        ->assertDontSee('user-99');
});
