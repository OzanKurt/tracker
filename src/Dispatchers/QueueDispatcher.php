<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Dispatchers;

use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Jobs\ProcessTrackerPayload;

final class QueueDispatcher implements DispatcherInterface
{
    public function dispatchPageView(Payload $payload): void
    {
        $this->dispatchJob('page_view', $payload, null, []);
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function dispatchEvent(Payload $payload, string $name, array $eventPayload): void
    {
        $this->dispatchJob('event', $payload, $name, $eventPayload);
    }

    public function flush(): void
    {
        // Nothing to flush for the queue dispatcher.
    }

    /**
     * @param  'page_view'|'event'  $kind
     * @param  array<string, mixed>  $eventPayload
     */
    private function dispatchJob(string $kind, Payload $payload, ?string $name, array $eventPayload): void
    {
        $connection = config('tracker.queue.connection');
        $queueName  = (string) config('tracker.queue.name', 'default');

        $pending = ProcessTrackerPayload::dispatch($kind, $payload->toArray(), $name, $eventPayload)
            ->onQueue($queueName);

        if (is_string($connection) && $connection !== '') {
            $pending->onConnection($connection);
        }
    }
}
