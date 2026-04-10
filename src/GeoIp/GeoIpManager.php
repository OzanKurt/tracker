<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;

class GeoIpManager
{
    private ?GeoIpProviderInterface $override = null;

    public function __construct(
        private readonly GeoIpCacheRepository $cache,
    ) {}

    public function lookup(string $ip): GeoIpResult
    {
        $hash = hash('sha256', $ip);

        $cached = $this->cache->find($hash);
        if ($cached !== null) {
            return new GeoIpResult(
                countryCode: $cached->country_code,
                countryName: $cached->country_name,
                city:        $cached->city,
                latitude:    $cached->latitude !== null ? (float) $cached->latitude : null,
                longitude:   $cached->longitude !== null ? (float) $cached->longitude : null,
            );
        }

        $provider = $this->provider();
        $result = $provider->lookup($ip);

        if ($result->countryCode !== null) {
            $this->cache->put(
                ipHash: $hash,
                geo:    $result->toArray(),
                provider: $provider->name(),
                ttlDays: (int) config('tracker.geoip.cache_ttl_days', 30),
            );
        }

        return $result;
    }

    public function setProviderOverride(GeoIpProviderInterface $provider): void
    {
        $this->override = $provider;
    }

    private function provider(): GeoIpProviderInterface
    {
        if ($this->override !== null) {
            return $this->override;
        }

        return match ((string) config('tracker.geoip.driver', 'null')) {
            'maxmind' => new MaxMindProvider(),
            'ipinfo'  => new IpInfoProvider(),
            'ipapi'   => new IpApiProvider(),
            default   => new NullProvider(),
        };
    }
}
