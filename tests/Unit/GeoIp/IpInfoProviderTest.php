<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use OzanKurt\Tracker\GeoIp\IpInfoProvider;

beforeEach(fn () => config()->set('tracker.geoip.ipinfo.token', 'fake-token'));

it('parses the ipinfo response shape', function () {
    Http::fake([
        'ipinfo.io/*' => Http::response([
            'ip' => '203.0.113.5',
            'country' => 'TR',
            'city' => 'Istanbul',
            'loc' => '41.0082,28.9784',
        ], 200),
    ]);

    $result = (new IpInfoProvider)->lookup('203.0.113.5');

    expect($result->countryCode)->toBe('TR')
        ->and($result->city)->toBe('Istanbul')
        ->and($result->latitude)->toBe(41.0082)
        ->and($result->longitude)->toBe(28.9784);
});

it('returns empty on http failure', function () {
    Http::fake(['ipinfo.io/*' => Http::response(null, 500)]);
    expect((new IpInfoProvider)->lookup('203.0.113.5')->countryCode)->toBeNull();
});

it('short-circuits and skips the HTTP call when the IP is malformed', function () {
    Http::fake();

    $result = (new IpInfoProvider)->lookup('not-an-ip/etc');

    expect($result->countryCode)->toBeNull();
    Http::assertNothingSent();
});
