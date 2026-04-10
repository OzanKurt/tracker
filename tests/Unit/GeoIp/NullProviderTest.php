<?php

declare(strict_types=1);

use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\GeoIp\NullProvider;

it('returns an empty result for any ip', function () {
    $provider = new NullProvider();
    $result = $provider->lookup('203.0.113.5');

    expect($result)->toBeInstanceOf(GeoIpResult::class)
        ->and($result->countryCode)->toBeNull()
        ->and($result->city)->toBeNull();
});

it('reports its name as null', function () {
    expect((new NullProvider())->name())->toBe('null');
});
