<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
});

it('renders the overview page when the viewTracker gate allows', function () {
    Gate::define('viewTracker', fn ($user = null) => true);

    $this->get('/tracker')
        ->assertOk()
        ->assertSee('Tracker');
});

it('returns 403 when the viewTracker gate denies', function () {
    Gate::define('viewTracker', fn ($user = null) => false);

    $this->get('/tracker')->assertForbidden();
});
