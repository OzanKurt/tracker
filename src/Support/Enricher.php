<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

use OzanKurt\Agent\Agent;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\GeoIp\GeoIpManager;

class Enricher
{
    public function __construct(
        private readonly GeoIpManager $geoIp,
        private readonly RefererParser $refererParser,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function enrich(Payload $payload): array
    {
        $agent = new Agent();
        $agent->setUserAgent($payload->userAgent);

        $deviceKind = $this->resolveDeviceKind($agent, $payload->userAgent);
        $platform   = (string) ($agent->platform() ?: 'unknown');
        $browser    = (string) ($agent->browser() ?: 'unknown');

        $host = (string) (parse_url($payload->url, PHP_URL_HOST) ?: '');
        $referer = $this->refererParser->parse($payload->referer, $host);

        $geo = $this->geoIp->lookup($payload->ip);

        $language = $this->preferredLanguage($payload->languageRange);

        $platformVersion = $this->safeVersion($agent, $platform);
        $browserVersion  = $this->safeVersion($agent, $browser);
        $deviceName      = $this->safeDevice($agent);

        return [
            'uuid'             => $payload->sessionId,
            'visitor_uuid'     => $payload->visitorUuid,
            'user_id'          => $payload->userId,
            'client_ip'        => $payload->ip,
            'user_agent'       => $payload->userAgent,

            'device_kind'         => $deviceKind,
            'device_model'        => is_string($deviceName) && $deviceName !== '' ? $deviceName : null,
            'device_platform'     => $platform,
            'device_platform_ver' => is_string($platformVersion) && $platformVersion !== '' ? $platformVersion : null,
            'browser'             => $browser,
            'browser_version'     => is_string($browserVersion) && $browserVersion !== '' ? $browserVersion : 'unknown',

            'language'       => $language,
            'language_range' => $payload->languageRange,

            'is_robot' => $agent->isRobot($payload->userAgent),

            'country_code' => $geo->countryCode,
            'country_name' => $geo->countryName,
            'city'         => $geo->city,
            'latitude'     => $geo->latitude,
            'longitude'    => $geo->longitude,

            'referer_url'         => $referer->url,
            'referer_domain'      => $referer->domain,
            'referer_medium'      => $referer->medium,
            'referer_source'      => $referer->source,
            'referer_search_term' => $referer->searchTerm,

            'started_at'       => $payload->capturedAt,
            'last_activity_at' => $payload->capturedAt,
        ];
    }

    private function safeVersion(Agent $agent, string $propertyName): float|string|bool
    {
        try {
            return $agent->version($propertyName);
        } catch (\Error) {
            return false;
        }
    }

    private function safeDevice(Agent $agent): string|bool
    {
        try {
            return $agent->device();
        } catch (\BadMethodCallException) {
            return false;
        }
    }

    private function resolveDeviceKind(Agent $agent, string $userAgent): string
    {
        if ($agent->isRobot($userAgent)) {
            return 'bot';
        }
        if ($agent->isTablet()) {
            return 'tablet';
        }
        if ($agent->isMobile()) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function preferredLanguage(string $range): string
    {
        if ($range === '') {
            return 'unknown';
        }

        $first = explode(',', $range, 2)[0];
        $first = trim(explode(';', $first, 2)[0]);

        return $first === '' ? 'unknown' : $first;
    }
}
