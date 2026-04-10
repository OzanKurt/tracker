<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\Session;

it('can be persisted and hydrated with casts', function () {
    $session = Session::create([
        'uuid'                => 'session-uuid-1',
        'visitor_uuid'        => 'visitor-uuid-1',
        'client_ip'           => '203.0.113.0',
        'user_agent'          => 'Mozilla/5.0',
        'device_kind'         => 'desktop',
        'device_platform'     => 'macOS',
        'browser'             => 'Chrome',
        'browser_version'     => '120.0',
        'language'            => 'en-US',
        'language_range'      => 'en-US,en;q=0.9',
        'is_robot'            => false,
        'latitude'            => 37.7749,
        'longitude'           => -122.4194,
        'started_at'          => now(),
        'last_activity_at'    => now(),
    ]);

    $fresh = Session::find($session->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->is_robot)->toBeFalse()
        ->and((float) $fresh->latitude)->toBe(37.7749)
        ->and($fresh->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
