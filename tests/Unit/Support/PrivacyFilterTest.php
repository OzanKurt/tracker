<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use OzanKurt\Tracker\Support\PrivacyFilter;

beforeEach(function () {
    config()->set('tracker.enabled', true);
    config()->set('tracker.privacy.respect_dnt', true);
    config()->set('tracker.privacy.anonymize_ip', false);
    config()->set('tracker.privacy.scrub_param_keys', []);
    config()->set('tracker.cookie.name', 'tracker_visitor');
    config()->set('tracker.routes.ignore', ['tracker', 'tracker/*', 'telescope', 'telescope/*']);
    // Keep the dashboard self-tracking guard off by default in this suite —
    // individual tests opt in to exercise it.
    config()->set('tracker.dashboard.enabled', false);
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

it('blocks bare ignored routes without a trailing segment', function () {
    $filter = new PrivacyFilter;
    expect($filter->shouldTrack(Request::create('/tracker')))->toBeFalse()
        ->and($filter->shouldTrack(Request::create('/telescope')))->toBeFalse();
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

it('returns the IP unchanged when anonymize_ip is off', function () {
    config()->set('tracker.privacy.anonymize_ip', false);
    $filter = new PrivacyFilter;

    expect($filter->anonymize('203.0.113.55'))->toBe('203.0.113.55')
        ->and($filter->anonymize('2001:db8:abcd:1234::1'))->toBe('2001:db8:abcd:1234::1');
});

it('masks the last IPv4 octet and the IPv6 /48 suffix when anonymize_ip is on', function () {
    config()->set('tracker.privacy.anonymize_ip', true);
    $filter = new PrivacyFilter;

    expect($filter->anonymize('203.0.113.55'))->toBe('203.0.113.0')
        ->and($filter->anonymize('2001:db8:abcd:1234::1'))->toBe('2001:db8:abcd::');
});

it('leaves malformed or empty IP strings alone when anonymizing', function () {
    config()->set('tracker.privacy.anonymize_ip', true);
    $filter = new PrivacyFilter;

    expect($filter->anonymize(''))->toBe('')
        ->and($filter->anonymize('not-an-ip'))->toBe('not-an-ip');
});

it('passes params through untouched when no scrub patterns are configured', function () {
    $filter = new PrivacyFilter;

    expect($filter->scrub(['token' => 'abc', 'q' => 'hello']))
        ->toBe(['token' => 'abc', 'q' => 'hello']);
});

it('redacts keys that match the configured scrub patterns', function () {
    config()->set('tracker.privacy.scrub_param_keys', ['token', '*secret*', 'password']);
    $filter = new PrivacyFilter;

    $result = $filter->scrub([
        'token' => 'aaa',
        'client_secret' => 'bbb',
        'password' => 'ccc',
        'q' => 'kept',
    ]);

    expect($result)->toBe([
        'token' => '[REDACTED]',
        'client_secret' => '[REDACTED]',
        'password' => '[REDACTED]',
        'q' => 'kept',
    ]);
});

it('auto-ignores the configured dashboard path so it does not self-track', function () {
    config()->set('tracker.routes.ignore', []);
    config()->set('tracker.dashboard.enabled', true);
    config()->set('tracker.dashboard.path', 'analytics');

    $filter = new PrivacyFilter;

    expect($filter->shouldTrack(Request::create('/analytics')))->toBeFalse()
        ->and($filter->shouldTrack(Request::create('/analytics/sessions')))->toBeFalse()
        ->and($filter->shouldTrack(Request::create('/dashboard')))->toBeTrue();
});
