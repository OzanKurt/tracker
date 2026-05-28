<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

use Illuminate\Support\Facades\Http;
use Throwable;

final class IpInfoProvider implements GeoIpProviderInterface
{
    public function lookup(string $ip): GeoIpResult
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return GeoIpResult::empty();
        }

        $token = (string) config('tracker.geoip.ipinfo.token', '');
        if ($token === '') {
            return GeoIpResult::empty();
        }

        try {
            $response = Http::timeout(3)->get("https://ipinfo.io/{$ip}", ['token' => $token]);
        } catch (Throwable) {
            return GeoIpResult::empty();
        }

        if (! $response->successful()) {
            return GeoIpResult::empty();
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        $lat = null;
        $lon = null;
        if (isset($data['loc']) && is_string($data['loc']) && str_contains($data['loc'], ',')) {
            [$latStr, $lonStr] = explode(',', $data['loc'], 2);
            $lat = (float) $latStr;
            $lon = (float) $lonStr;
        }

        return new GeoIpResult(
            countryCode: isset($data['country']) ? (string) $data['country'] : null,
            countryName: null,
            city: isset($data['city']) ? (string) $data['city'] : null,
            latitude: $lat,
            longitude: $lon,
        );
    }

    public function name(): string
    {
        return 'ipinfo';
    }
}
