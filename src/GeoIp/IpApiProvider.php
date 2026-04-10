<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

use Illuminate\Support\Facades\Http;
use Throwable;

final class IpApiProvider implements GeoIpProviderInterface
{
    public function lookup(string $ip): GeoIpResult
    {
        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,country,countryCode,city,lat,lon',
            ]);
        } catch (Throwable) {
            return GeoIpResult::empty();
        }

        if (! $response->successful()) {
            return GeoIpResult::empty();
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        if (($data['status'] ?? null) !== 'success') {
            return GeoIpResult::empty();
        }

        return new GeoIpResult(
            countryCode: isset($data['countryCode']) ? (string) $data['countryCode'] : null,
            countryName: isset($data['country']) ? (string) $data['country'] : null,
            city: isset($data['city']) ? (string) $data['city'] : null,
            latitude: isset($data['lat']) ? (float) $data['lat'] : null,
            longitude: isset($data['lon']) ? (float) $data['lon'] : null,
        );
    }

    public function name(): string
    {
        return 'ipapi';
    }
}
