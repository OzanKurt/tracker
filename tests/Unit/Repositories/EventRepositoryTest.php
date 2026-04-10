<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Repositories\EventRepository;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

it('creates an event for a session', function () {
    $session = Session::create([
        'uuid'             => 'sess-ev',
        'visitor_uuid'     => 'vis-ev',
        'client_ip'        => '203.0.113.20',
        'user_agent'       => 'UA',
        'device_kind'      => 'desktop',
        'device_platform'  => 'Linux',
        'browser'          => 'Chrome',
        'browser_version'  => '120',
        'language'         => 'en',
        'language_range'   => 'en-US',
        'started_at'       => now(),
        'last_activity_at' => now(),
    ]);

    $repo = new EventRepository();

    $event = $repo->create([
        'session_id' => $session->id,
        'name'       => 'signup.completed',
        'payload'    => ['plan' => 'pro'],
        'created_at' => now(),
    ]);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->name)->toBe('signup.completed')
        ->and($event->payload)->toBe(['plan' => 'pro']);
});
