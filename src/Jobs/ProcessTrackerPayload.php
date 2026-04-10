<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Support\Pipeline;

class ProcessTrackerPayload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  'page_view'|'event'  $kind
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $eventPayload
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $payload,
        public readonly ?string $name = null,
        public readonly array $eventPayload = [],
    ) {}

    public function handle(Pipeline $pipeline): void
    {
        $payloadObj = Payload::fromArray($this->payload);

        if ($this->kind === 'event' && $this->name !== null) {
            $pipeline->processEvent($payloadObj, $this->name, $this->eventPayload);

            return;
        }

        $pipeline->process($payloadObj);
    }
}
