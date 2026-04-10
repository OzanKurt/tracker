<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Dispatchers;

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Support\Pipeline;

final class SyncDispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly Pipeline $pipeline,
    ) {}

    public function dispatchPageView(Payload $payload): void
    {
        $this->pipeline->process($payload);
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function dispatchEvent(Payload $payload, string $name, array $eventPayload): void
    {
        $this->pipeline->processEvent($payload, $name, $eventPayload);
    }

    public function flush(): void
    {
        // Sync dispatcher already processed inline — nothing to flush.
    }
}
