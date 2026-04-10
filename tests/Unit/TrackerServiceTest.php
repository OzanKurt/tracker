<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use OzanKurt\Tracker\Jobs\ProcessTrackerPayload;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Tracker;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__.'/../../database/migrations'));

it('returns online users filtered by the last activity window', function () {
    Session::create([
        'uuid' => 'recent', 'visitor_uuid' => 'v1', 'client_ip' => '1.1.1.1',
        'user_agent' => 'UA', 'device_kind' => 'desktop', 'device_platform' => 'macOS',
        'browser' => 'Chrome', 'browser_version' => '120',
        'language' => 'en', 'language_range' => 'en-US',
        'started_at' => now()->subMinutes(1), 'last_activity_at' => now()->subMinutes(1),
    ]);
    Session::create([
        'uuid' => 'stale', 'visitor_uuid' => 'v2', 'client_ip' => '2.2.2.2',
        'user_agent' => 'UA', 'device_kind' => 'desktop', 'device_platform' => 'Windows',
        'browser' => 'Firefox', 'browser_version' => '125',
        'language' => 'tr', 'language_range' => 'tr-TR',
        'started_at' => now()->subHours(2), 'last_activity_at' => now()->subHours(2),
    ]);

    $online = app(Tracker::class)->onlineUsers(3);

    expect($online)->toHaveCount(1)
        ->and($online->first()->uuid)->toBe('recent');
});

it('logs an event via the active dispatcher', function () {
    config()->set('tracker.dispatcher', 'queue');
    Queue::fake();

    app(Tracker::class)->logEvent('signup.completed', ['plan' => 'pro']);

    Queue::assertPushed(ProcessTrackerPayload::class, function (ProcessTrackerPayload $job) {
        return $job->kind === 'event' && $job->name === 'signup.completed';
    });
});
