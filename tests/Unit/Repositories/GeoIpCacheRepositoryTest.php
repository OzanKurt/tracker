<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\GeoIpCache;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

it('returns null on cache miss', function () {
    $repo = new GeoIpCacheRepository();
    expect($repo->find('missing-hash'))->toBeNull();
});

it('stores and retrieves a cache entry', function () {
    $repo = new GeoIpCacheRepository();

    $repo->put('hash-1', [
        'country_code' => 'TR',
        'country_name' => 'Türkiye',
        'city'         => 'Istanbul',
        'latitude'     => 41.01,
        'longitude'    => 28.97,
    ], 'ipapi', ttlDays: 30);

    $entry = $repo->find('hash-1');
    expect($entry)->toBeInstanceOf(GeoIpCache::class)
        ->and($entry->country_code)->toBe('TR')
        ->and((float) $entry->latitude)->toBe(41.01);
});

it('ignores expired cache entries', function () {
    GeoIpCache::create([
        'ip_hash'      => 'expired-hash',
        'country_code' => 'US',
        'provider'     => 'ipapi',
        'cached_until' => now()->subDay(),
        'created_at'   => now()->subDays(10),
    ]);

    $repo = new GeoIpCacheRepository();
    expect($repo->find('expired-hash'))->toBeNull();
});
