<?php

declare(strict_types=1);

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use OzanKurt\Tracker\Facades\Tracker;
use OzanKurt\Tracker\Http\Middleware\TrackRequests;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    config()->set('tracker.dispatcher', 'sync');
    config()->set('session.driver', 'array');

    Route::middleware([StartSession::class, TrackRequests::class])
        ->get('/hello', function () {
            Tracker::logEvent('hello.viewed', ['source' => 'test']);

            return 'hi';
        });
});

it('tracks a session, page view and logged event via the facade', function () {
    $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120'])
        ->get('/hello')
        ->assertOk();

    expect(Session::count())->toBe(1)
        ->and(PageView::count())->toBe(1)
        ->and(Event::count())->toBe(1);

    $event = Event::first();
    expect($event->name)->toBe('hello.viewed')
        ->and($event->payload)->toBe(['source' => 'test']);
});
