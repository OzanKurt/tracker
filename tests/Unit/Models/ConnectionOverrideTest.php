<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\GeoIpCache;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    // Register a second in-memory SQLite connection so the test can verify
    // the override resolves cleanly through Laravel's DatabaseManager.
    config()->set('database.connections.alt_tracker', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
});

afterEach(function () {
    config()->set('tracker.connection', null);
});

it('resolves models on the default connection when tracker.connection is null', function () {
    config()->set('tracker.connection', null);

    expect((new Session)->getConnectionName())->toBeNull()
        ->and((new PageView)->getConnectionName())->toBeNull()
        ->and((new Event)->getConnectionName())->toBeNull()
        ->and((new GeoIpCache)->getConnectionName())->toBeNull();
});

it('routes models to a configured connection when tracker.connection is set', function () {
    config()->set('tracker.connection', 'alt_tracker');

    expect((new Session)->getConnectionName())->toBe('alt_tracker')
        ->and((new PageView)->getConnectionName())->toBe('alt_tracker')
        ->and((new Event)->getConnectionName())->toBe('alt_tracker')
        ->and((new GeoIpCache)->getConnectionName())->toBe('alt_tracker');
});

it('treats an empty string config as "use default connection"', function () {
    config()->set('tracker.connection', '');

    expect((new Session)->getConnectionName())->toBeNull();
});
