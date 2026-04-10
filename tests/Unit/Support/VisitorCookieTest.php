<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use OzanKurt\Tracker\Support\VisitorCookie;
use Symfony\Component\HttpFoundation\Cookie;

beforeEach(function () {
    config()->set('tracker.cookie.name', 'tracker_visitor');
    config()->set('tracker.cookie.lifetime_days', 365);
    config()->set('tracker.cookie.secure', true);
    config()->set('tracker.cookie.http_only', true);
    config()->set('tracker.cookie.same_site', 'lax');
});

it('generates a new uuid when the cookie is missing', function () {
    $request = Request::create('/');
    $cookie = new VisitorCookie;

    $uuid = $cookie->readOrIssue($request);

    expect($uuid)->toBeString()->toHaveLength(36)
        ->and($cookie->issuedCookie())->toBeInstanceOf(Cookie::class)
        ->and($cookie->issuedCookie()->getName())->toBe('tracker_visitor')
        ->and($cookie->issuedCookie()->getValue())->toBe($uuid);
});

it('reads an existing cookie and does not issue a new one', function () {
    $existing = '11111111-2222-3333-4444-555555555555';
    $request = Request::create('/');
    $request->cookies->set('tracker_visitor', $existing);

    $cookie = new VisitorCookie;
    $uuid = $cookie->readOrIssue($request);

    expect($uuid)->toBe($existing)
        ->and($cookie->issuedCookie())->toBeNull();
});
