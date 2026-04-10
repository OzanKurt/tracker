<?php

declare(strict_types=1);

use OzanKurt\Tracker\Models\GeoIpCache;

it('persists a geoip cache entry and exposes typed columns', function () {
    $entry = GeoIpCache::create([
        'ip_hash'      => str_repeat('a', 64),
        'country_code' => 'TR',
        'country_name' => 'Türkiye',
        'city'         => 'Istanbul',
        'latitude'     => 41.0082,
        'longitude'    => 28.9784,
        'provider'     => 'ipapi',
        'cached_until' => now()->addDays(30),
        'created_at'   => now(),
    ]);

    $fresh = GeoIpCache::find($entry->id);

    expect($fresh->country_code)->toBe('TR')
        ->and((float) $fresh->latitude)->toBe(41.0082)
        ->and($fresh->cached_until)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('enforces unique ip_hash', function () {
    $hash = str_repeat('b', 64);

    GeoIpCache::create([
        'ip_hash' => $hash,
        'provider' => 'null',
        'cached_until' => now()->addDay(),
        'created_at' => now(),
    ]);

    expect(fn () => GeoIpCache::create([
        'ip_hash' => $hash,
        'provider' => 'null',
        'cached_until' => now()->addDay(),
        'created_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
