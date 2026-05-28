<?php

declare(strict_types=1);

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\GeoIp\GeoIpManager;
use OzanKurt\Tracker\GeoIp\GeoIpProviderInterface;
use OzanKurt\Tracker\GeoIp\GeoIpResult;
use OzanKurt\Tracker\Models\Event;
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
use OzanKurt\Tracker\Support\PrivacyFilter;
use OzanKurt\Tracker\Support\RefererParser;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
    config()->set('tracker.privacy.drop_bots', true);
});

function makePipelineForPipelineTest(): Pipeline
{
    $geo = new GeoIpManager(new GeoIpCacheRepository);
    $geo->setProviderOverride(new class implements GeoIpProviderInterface
    {
        public function lookup(string $ip): GeoIpResult
        {
            return GeoIpResult::empty();
        }

        public function name(): string
        {
            return 'stub';
        }
    });

    $repos = new RepositoryManager(
        sessions: new SessionRepository,
        pageViews: new PageViewRepository,
        events: new EventRepository,
        geoIpCache: new GeoIpCacheRepository,
    );

    return new Pipeline(
        botFilter: new BotFilter,
        enricher: new Enricher($geo, new RefererParser, new PrivacyFilter),
        repositories: $repos,
    );
}

function makeBrowserPayload(string $sessionId = 'sess-1'): Payload
{
    return Payload::fromArray([
        'ip' => '203.0.113.60',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36',
        'method' => 'GET',
        'url' => 'https://myapp.test/dashboard',
        'path' => '/dashboard',
        'route_name' => 'dashboard',
        'route_action' => null,
        'route_params' => [],
        'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => $sessionId,
        'user_id' => 7,
        'referer' => null,
        'language_range' => 'en-US,en;q=0.9',
        'captured_at' => now()->toIso8601String(),
    ]);
}

it('processes a browser request into a session and page view', function () {
    $pipeline = makePipelineForPipelineTest();
    $pipeline->process(makeBrowserPayload());

    expect(Session::count())->toBe(1)
        ->and(PageView::count())->toBe(1);

    $session = Session::first();
    expect($session->user_id)->toBe(7)
        ->and($session->page_views_count)->toBe(1);
});

it('reuses an existing session on subsequent page views', function () {
    $pipeline = makePipelineForPipelineTest();
    $pipeline->process(makeBrowserPayload());
    $pipeline->process(makeBrowserPayload());

    expect(Session::count())->toBe(1)
        ->and(PageView::count())->toBe(2);
    expect(Session::first()->page_views_count)->toBe(2);
});

it('drops bot requests when drop_bots is true', function () {
    $pipeline = makePipelineForPipelineTest();

    $payload = Payload::fromArray([
        'ip' => '203.0.113.60',
        'user_agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        'method' => 'GET',
        'url' => 'https://myapp.test/',
        'path' => '/',
        'route_name' => null,
        'route_action' => null,
        'route_params' => [],
        'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => 'sess-bot',
        'user_id' => null,
        'referer' => null,
        'language_range' => '',
        'captured_at' => now()->toIso8601String(),
    ]);

    $pipeline->process($payload);

    expect(Session::count())->toBe(0)
        ->and(PageView::count())->toBe(0);
});

it('processes a custom event tied to the current session', function () {
    $pipeline = makePipelineForPipelineTest();
    $pipeline->process(makeBrowserPayload());
    $pipeline->processEvent(makeBrowserPayload(), 'signup.completed', ['plan' => 'pro']);

    expect(Event::count())->toBe(1);
    $event = Event::first();
    expect($event->name)->toBe('signup.completed')
        ->and($event->payload)->toBe(['plan' => 'pro'])
        ->and(Session::first()->events_count)->toBe(1);
});
