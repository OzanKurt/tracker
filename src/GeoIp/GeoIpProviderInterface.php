<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

interface GeoIpProviderInterface
{
    public function lookup(string $ip): GeoIpResult;

    public function name(): string;
}
