<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

final class NullProvider implements GeoIpProviderInterface
{
    public function lookup(string $ip): GeoIpResult
    {
        return GeoIpResult::empty();
    }

    public function name(): string
    {
        return 'null';
    }
}
