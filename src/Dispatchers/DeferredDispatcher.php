<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Dispatchers;

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Support\Pipeline;

final class DeferredDispatcher implements DispatcherInterface
{
    /** @var list<array{kind: 'page_view'|'event', payload: Payload, name?: string, eventPayload?: array<string, mixed>}> */
    private array $queue = [];

    public function __construct(
        private readonly Pipeline $pipeline,
    ) {}

    public function dispatchPageView(Payload $payload): void
    {
        $this->queue[] = ['kind' => 'page_view', 'payload' => $payload];
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function dispatchEvent(Payload $payload, string $name, array $eventPayload): void
    {
        $this->queue[] = [
            'kind' => 'event',
            'payload' => $payload,
            'name' => $name,
            'eventPayload' => $eventPayload,
        ];
    }

    public function flush(): void
    {
        foreach ($this->queue as $entry) {
            if ($entry['kind'] === 'page_view') {
                $this->pipeline->process($entry['payload']);
                continue;
            }

            $this->pipeline->processEvent(
                $entry['payload'],
                $entry['name'] ?? '',
                $entry['eventPayload'] ?? [],
            );
        }

        $this->queue = [];
    }
}
