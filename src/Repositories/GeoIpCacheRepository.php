<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Repositories;

use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Models\GeoIpCache;

class GeoIpCacheRepository
{
    public function find(string $ipHash): ?GeoIpCache
    {
        return GeoIpCache::where('ip_hash', $ipHash)
            ->where('cached_until', '>=', Carbon::now())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $geo
     */
    public function put(string $ipHash, array $geo, string $provider, int $ttlDays): GeoIpCache
    {
        return GeoIpCache::updateOrCreate(
            ['ip_hash' => $ipHash],
            [
                'country_code' => $geo['country_code'] ?? null,
                'country_name' => $geo['country_name'] ?? null,
                'city' => $geo['city'] ?? null,
                'latitude' => $geo['latitude'] ?? null,
                'longitude' => $geo['longitude'] ?? null,
                'provider' => $provider,
                'cached_until' => Carbon::now()->addDays($ttlDays),
                'created_at' => Carbon::now(),
            ],
        );
    }
}
