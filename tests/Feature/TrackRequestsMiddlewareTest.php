<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OzanKurt\Tracker\Http\Middleware\TrackRequests;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    config()->set('tracker.dispatcher', 'sync');
    config()->set('tracker.privacy.drop_bots', true);

    Route::middleware(TrackRequests::class)->get('/demo', fn () => 'ok')->name('demo');
});

it('records a session and page view for a normal request', function () {
    $response = $this->withServerVariables([
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36',
    ])->get('/demo');

    $response->assertOk()->assertSee('ok');

    expect(Session::count())->toBe(1)
        ->and(PageView::count())->toBe(1);

    $view = PageView::first();
    expect($view->path)->toBe('/demo');
});

it('does not record when tracker is disabled', function () {
    config()->set('tracker.enabled', false);

    $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120'])->get('/demo');

    expect(Session::count())->toBe(0);
});

it('skips ignored routes', function () {
    config()->set('tracker.routes.ignore', ['demo']);

    $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120'])->get('/demo');

    expect(Session::count())->toBe(0);
});
