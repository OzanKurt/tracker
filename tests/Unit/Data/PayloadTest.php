<?php

declare(strict_types=1);

use OzanKurt\Tracker\Data\Payload;

it('constructs from an array and round-trips to array', function () {
    $data = [
        'ip' => '203.0.113.5',
        'user_agent' => 'Mozilla/5.0',
        'method' => 'GET',
        'url' => 'https://example.com/dashboard?tab=1',
        'path' => '/dashboard',
        'route_name' => 'dashboard',
        'route_action' => 'App\Http\Controllers\DashboardController@index',
        'route_params' => ['tab' => '1'],
        'query_params' => ['tab' => '1'],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => '22222222-2222-2222-2222-222222222222',
        'user_id' => 42,
        'referer' => 'https://google.com/search?q=tracker',
        'language_range' => 'en-US,en;q=0.9',
        'captured_at' => '2026-04-10T12:00:00+00:00',
    ];

    $payload = Payload::fromArray($data);

    expect($payload->ip)->toBe('203.0.113.5')
        ->and($payload->userId)->toBe(42)
        ->and($payload->routeParams)->toBe(['tab' => '1'])
        ->and($payload->toArray())->toBe($data);
});

it('allows nullable fields', function () {
    $payload = Payload::fromArray([
        'ip' => '203.0.113.5',
        'user_agent' => 'Mozilla/5.0',
        'method' => 'GET',
        'url' => 'https://example.com/',
        'path' => '/',
        'route_name' => null,
        'route_action' => null,
        'route_params' => [],
        'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => '22222222-2222-2222-2222-222222222222',
        'user_id' => null,
        'referer' => null,
        'language_range' => '',
        'captured_at' => '2026-04-10T12:00:00+00:00',
    ]);

    expect($payload->userId)->toBeNull()
        ->and($payload->routeName)->toBeNull()
        ->and($payload->referer)->toBeNull();
});
