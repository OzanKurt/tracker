<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OzanKurt\Tracker\Http\Middleware\Authorize;

afterEach(function () {
    // Restore the environment to 'testing' so teardown migrations don't ask for confirmation.
    app()['env'] = 'testing';
});

it('allows the request when the viewTracker gate returns true', function () {
    Gate::define('viewTracker', fn ($user = null) => true);

    $middleware = new Authorize();
    $response = $middleware->handle(Request::create('/tracker'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('aborts with 403 when the viewTracker gate returns false', function () {
    Gate::define('viewTracker', fn ($user = null) => false);

    $middleware = new Authorize();

    expect(fn () => $middleware->handle(Request::create('/tracker'), fn () => new Response('ok')))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('allows in local environment when no gate is defined', function () {
    app()['env'] = 'local';

    $middleware = new Authorize();
    $response = $middleware->handle(Request::create('/tracker'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('denies in production environment when no gate is defined', function () {
    app()['env'] = 'production';

    $middleware = new Authorize();

    expect(fn () => $middleware->handle(Request::create('/tracker'), fn () => new Response('ok')))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
