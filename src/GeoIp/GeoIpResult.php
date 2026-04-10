<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\GeoIp;

final class GeoIpResult
{
    public function __construct(
        public readonly ?string $countryCode,
        public readonly ?string $countryName,
        public readonly ?string $city,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'country_code' => $this->countryCode,
            'country_name' => $this->countryName,
            'city' => $this->city,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
