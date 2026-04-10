<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Dispatchers;

use OzanKurt\Tracker\Data\Payload;

interface DispatcherInterface
{
    public function dispatchPageView(Payload $payload): void;

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function dispatchEvent(Payload $payload, string $name, array $eventPayload): void;

    public function flush(): void;
}
