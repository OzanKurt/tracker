<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

use Throwable;

final class MaxMindProvider implements GeoIpProviderInterface
{
    public function lookup(string $ip): GeoIpResult
    {
        $databasePath = (string) config('tracker.geoip.maxmind.database', '');

        if ($databasePath === '' || ! is_readable($databasePath)) {
            return GeoIpResult::empty();
        }

        if (! class_exists(\GeoIp2\Database\Reader::class)) {
            return GeoIpResult::empty();
        }

        try {
            $reader = new \GeoIp2\Database\Reader($databasePath);
            $record = $reader->city($ip);
        } catch (Throwable) {
            return GeoIpResult::empty();
        }

        return new GeoIpResult(
            countryCode: $record->country->isoCode,
            countryName: $record->country->name,
            city:        $record->city->name,
            latitude:    $record->location->latitude !== null ? (float) $record->location->latitude : null,
            longitude:   $record->location->longitude !== null ? (float) $record->location->longitude : null,
        );
    }

    public function name(): string
    {
        return 'maxmind';
    }
}
