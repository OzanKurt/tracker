<?php

declare(strict_types=1);

use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

it('delegates to the active provider when the cache misses', function () {
    config()->set('tracker.geoip.driver', 'null');
    config()->set('tracker.geoip.cache_ttl_days', 30);

    $manager = new GeoIpManager(new GeoIpCacheRepository());
    $result = $manager->lookup('203.0.113.5');

    expect($result)->toBeInstanceOf(GeoIpResult::class)
        ->and($result->countryCode)->toBeNull();
});

it('caches a non-empty result and returns it on subsequent lookups', function () {
    config()->set('tracker.geoip.driver', 'test-stub');
    config()->set('tracker.geoip.cache_ttl_days', 30);

    $calls = 0;
    $stub = new class($calls) implements \OzanKurt\Tracker\GeoIp\GeoIpProviderInterface {
        public function __construct(public int &$calls) {}
        public function lookup(string $ip): GeoIpResult
        {
            $this->calls++;
            return new GeoIpResult('TR', 'Türkiye', 'Istanbul', 41.01, 28.97);
        }
        public function name(): string { return 'test-stub'; }
    };

    $manager = new GeoIpManager(new GeoIpCacheRepository());
    $manager->setProviderOverride($stub);

    $first  = $manager->lookup('203.0.113.99');
    $second = $manager->lookup('203.0.113.99');

    expect($first->countryCode)->toBe('TR')
        ->and($second->countryCode)->toBe('TR')
        ->and($calls)->toBe(1);
});
