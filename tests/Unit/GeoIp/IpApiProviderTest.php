<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use OzanKurt\Tracker\GeoIp\IpApiProvider;

it('returns geo data on a successful lookup', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status'      => 'success',
            'countryCode' => 'TR',
            'country'     => 'Turkey',
            'city'        => 'Istanbul',
            'lat'         => 41.0082,
            'lon'         => 28.9784,
        ], 200),
    ]);

    $result = (new IpApiProvider())->lookup('203.0.113.5');

    expect($result->countryCode)->toBe('TR')
        ->and($result->countryName)->toBe('Turkey')
        ->and($result->city)->toBe('Istanbul')
        ->and($result->latitude)->toBe(41.0082)
        ->and($result->longitude)->toBe(28.9784);
});

it('returns an empty result when the api returns failure', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(['status' => 'fail', 'message' => 'reserved range'], 200),
    ]);

    $result = (new IpApiProvider())->lookup('10.0.0.1');

    expect($result->countryCode)->toBeNull();
});

it('returns an empty result on http error', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(null, 500),
    ]);

    $result = (new IpApiProvider())->lookup('203.0.113.5');
    expect($result->countryCode)->toBeNull();
});
