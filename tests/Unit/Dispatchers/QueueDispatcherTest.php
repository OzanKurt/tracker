<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Dispatchers\QueueDispatcher;
use OzanKurt\Tracker\Jobs\ProcessTrackerPayload;

it('pushes a ProcessTrackerPayload job on page view dispatch', function () {
    Queue::fake();

    $dispatcher = new QueueDispatcher();

    $payload = Payload::fromArray([
        'ip' => '203.0.113.80', 'user_agent' => 'UA',
        'method' => 'GET', 'url' => 'https://app.test/', 'path' => '/',
        'route_name' => null, 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => 'q-1', 'user_id' => null, 'referer' => null,
        'language_range' => '', 'captured_at' => now()->toIso8601String(),
    ]);

    $dispatcher->dispatchPageView($payload);

    Queue::assertPushed(ProcessTrackerPayload::class, function (ProcessTrackerPayload $job) use ($payload) {
        return $job->kind === 'page_view'
            && $job->payload['session_id'] === $payload->sessionId;
    });
});

it('pushes a ProcessTrackerPayload job on event dispatch', function () {
    Queue::fake();

    $dispatcher = new QueueDispatcher();

    $payload = Payload::fromArray([
        'ip' => '203.0.113.80', 'user_agent' => 'UA',
        'method' => 'GET', 'url' => 'https://app.test/', 'path' => '/',
        'route_name' => null, 'route_action' => null,
        'route_params' => [], 'query_params' => [],
        'visitor_uuid' => '11111111-1111-1111-1111-111111111111',
        'session_id' => 'q-1', 'user_id' => null, 'referer' => null,
        'language_range' => '', 'captured_at' => now()->toIso8601String(),
    ]);

    $dispatcher->dispatchEvent($payload, 'signup.completed', ['plan' => 'pro']);

    Queue::assertPushed(ProcessTrackerPayload::class, function (ProcessTrackerPayload $job) {
        return $job->kind === 'event' && $job->name === 'signup.completed';
    });
});
