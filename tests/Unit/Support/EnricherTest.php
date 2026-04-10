<?php

declare(strict_types=1);

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\GeoIp\GeoIpProviderInterface;
use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;
use OzanKurt\Tracker\Support\Enricher;
use OzanKurt\Tracker\Support\RefererParser;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations'));

it('enriches a payload with device, geo and referer data', function () {
    $geoManager = new GeoIpManager(new GeoIpCacheRepository);
    $geoManager->setProviderOverride(new class implements GeoIpProviderInterface
    {
        public function lookup(string $ip): GeoIpResult
        {
            return new GeoIpResult('TR', 'Türkiye', 'Istanbul', 41.01, 28.97);
        }

        public function name(): string
        {
            return 'stub';
        }
    });

    $enricher = new Enricher($geoManager, new RefererParser);

    $payload = Payload::fromArray([
        'ip' => '203.0.113.50',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Chrome/120.0.0.0 Safari/537.36',
        'method' => 'GET',
        'url' => 'https://myapp.test/dashboard',
        'path' => '/dashboard',
        'route_name' => 'dashboard',
        'route_action' => null,
        'route_params' => [],
        'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => '22222222-2222-2222-2222-222222222222',
        'user_id' => null,
        'referer' => 'https://www.google.com/search?q=ozankurt',
        'language_range' => 'en-US,en;q=0.9',
        'captured_at' => '2026-04-10T12:00:00+00:00',
    ]);

    $data = $enricher->enrich($payload);

    expect($data['client_ip'])->toBe('203.0.113.50')
        ->and($data['browser'])->not->toBe('')
        ->and($data['country_code'])->toBe('TR')
        ->and($data['city'])->toBe('Istanbul')
        ->and($data['referer_medium'])->toBe('search')
        ->and($data['referer_source'])->toBe('google')
        ->and($data['referer_search_term'])->toBe('ozankurt')
        ->and($data['language'])->toBe('en-US');
});
