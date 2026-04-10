<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
    Gate::define('viewTracker', fn ($user = null) => true);
});

it('renders the events listing and filters by name', function () {
    $session = Session::create([
        'uuid' => 'ev-1', 'visitor_uuid' => 'v', 'client_ip' => '1.1.1.1',
        'user_agent' => 'UA', 'device_kind' => 'desktop', 'device_platform' => 'macOS',
        'browser' => 'Chrome', 'browser_version' => '120',
        'language' => 'en', 'language_range' => 'en-US',
        'started_at' => now(), 'last_activity_at' => now(),
    ]);

    Event::create(['session_id' => $session->id, 'name' => 'signup.completed', 'payload' => ['plan' => 'pro'], 'created_at' => now()]);
    Event::create(['session_id' => $session->id, 'name' => 'feature.used', 'payload' => [], 'created_at' => now()]);

    $this->get('/tracker/events')
        ->assertOk()
        ->assertSee('signup.completed')
        ->assertSee('feature.used');

    $this->get('/tracker/events?name=signup.completed')
        ->assertOk()
        ->assertSee('signup.completed')
        ->assertDontSee('feature.used');
});
