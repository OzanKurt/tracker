<?php

declare(strict_types=1);

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Dispatchers\DeferredDispatcher;
use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Repositories\EventRepository;
use OzanKurt\Tracker\Repositories\GeoIpCacheRepository;
use OzanKurt\Tracker\Repositories\PageViewRepository;
use OzanKurt\Tracker\Repositories\RepositoryManager;
use OzanKurt\Tracker\Repositories\SessionRepository;
use OzanKurt\Tracker\Support\BotFilter;
use OzanKurt\Tracker\Support\Enricher;
use OzanKurt\Tracker\Support\Pipeline;
use OzanKurt\Tracker\Support\RefererParser;

beforeEach(fn () => $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations'));

function makeDeferredPipeline(): Pipeline
{
    $geo = new GeoIpManager(new GeoIpCacheRepository());
    $geo->setProviderOverride(new class implements \OzanKurt\Tracker\GeoIp\GeoIpProviderInterface {
        public function lookup(string $ip): GeoIpResult { return GeoIpResult::empty(); }
        public function name(): string { return 'stub'; }
    });

    return new Pipeline(
        botFilter: new BotFilter(),
        enricher:  new Enricher($geo, new RefererParser()),
        repositories: new RepositoryManager(
            sessions:  new SessionRepository(),
            pageViews: new PageViewRepository(),
            events:    new EventRepository(),
            geoIpCache: new GeoIpCacheRepository(),
        ),
    );
}

it('does not process until flush is called', function () {
    $dispatcher = new DeferredDispatcher(makeDeferredPipeline());

    $payload = Payload::fromArray([
        'ip' => '203.0.113.90', 'user_agent' => 'Mozilla/5.0 Chrome/120',
        'method' => 'GET', 'url' => 'https://app.test/', 'path' => '/',
        'route_name' => null, 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => 'def-1', 'user_id' => null, 'referer' => null,
        'language_range' => 'en-US', 'captured_at' => now()->toIso8601String(),
    ]);

    $dispatcher->dispatchPageView($payload);

    expect(Session::count())->toBe(0);

    $dispatcher->flush();

    expect(Session::count())->toBe(1)
        ->and(PageView::count())->toBe(1);
});
