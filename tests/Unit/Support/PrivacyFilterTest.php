<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use OzanKurt\Tracker\Support\PrivacyFilter;

beforeEach(function () {
    config()->set('tracker.enabled', true);
    config()->set('tracker.privacy.respect_dnt', true);
    config()->set('tracker.cookie.name', 'tracker_visitor');
    config()->set('tracker.routes.ignore', ['tracker/*', 'telescope/*']);
});

it('allows normal requests', function () {
    $filter = new PrivacyFilter;
    $request = Request::create('/dashboard');
    expect($filter->shouldTrack($request))->toBeTrue();
});

it('blocks when globally disabled', function () {
    config()->set('tracker.enabled', false);
    $filter = new PrivacyFilter;
    expect($filter->shouldTrack(Request::create('/dashboard')))->toBeFalse();
});

it('blocks ignored routes by glob', function () {
    $filter = new PrivacyFilter;
    expect($filter->shouldTrack(Request::create('/tracker/sessions')))->toBeFalse()
        ->and($filter->shouldTrack(Request::create('/telescope/requests')))->toBeFalse()
        ->and($filter->shouldTrack(Request::create('/dashboard')))->toBeTrue();
});

it('blocks when DNT header is 1 and respect_dnt is true', function () {
    $filter = new PrivacyFilter;
    $request = Request::create('/dashboard');
    $request->headers->set('DNT', '1');
    expect($filter->shouldTrack($request))->toBeFalse();
});

it('allows DNT when respect_dnt is false', function () {
    config()->set('tracker.privacy.respect_dnt', false);
    $filter = new PrivacyFilter;
    $request = Request::create('/dashboard');
    $request->headers->set('DNT', '1');
    expect($filter->shouldTrack($request))->toBeTrue();
});

it('blocks when the opt-out cookie is present', function () {
    $filter = new PrivacyFilter;
    $request = Request::create('/dashboard');
    $request->cookies->set('tracker_visitor_optout', '1');
    expect($filter->shouldTrack($request))->toBeFalse();
});
