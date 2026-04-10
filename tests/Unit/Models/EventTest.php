<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\Session;

it('persists events with json payload and belongs to a session', function () {
    $session = Session::create([
        'uuid' => 'session-uuid-ev',
        'visitor_uuid' => 'visitor-uuid-ev',
        'client_ip' => '203.0.113.2',
        'user_agent' => 'Mozilla/5.0',
        'device_kind' => 'desktop',
        'device_platform' => 'Linux',
        'browser' => 'Chrome',
        'browser_version' => '121.0',
        'language' => 'tr-TR',
        'language_range' => 'tr-TR,tr;q=0.9',
        'started_at' => now(),
        'last_activity_at' => now(),
    ]);

    $event = Event::create([
        'session_id' => $session->id,
        'name' => 'signup.completed',
        'payload' => ['plan' => 'pro', 'referrer' => 'blog'],
        'created_at' => now(),
    ]);

    $fresh = Event::find($event->id);

    expect($fresh->name)->toBe('signup.completed')
        ->and($fresh->payload)->toBe(['plan' => 'pro', 'referrer' => 'blog'])
        ->and($fresh->session->id)->toBe($session->id);
});
