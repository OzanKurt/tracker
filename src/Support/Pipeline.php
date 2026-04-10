<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Support;

use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Data\Payload;
use OzanKurt\Tracker\Repositories\RepositoryManager;

class Pipeline
{
    public function __construct(
        private readonly BotFilter $botFilter,
        private readonly Enricher $enricher,
        private readonly RepositoryManager $repositories,
    ) {}

    public function process(Payload $payload): void
    {
        if ($this->shouldDrop($payload)) {
            return;
        }

        $sessionAttrs = $this->enricher->enrich($payload);
        $session = $this->repositories->sessions->findOrCreateByUuid($payload->sessionId, $sessionAttrs);

        $this->repositories->pageViews->create([
            'session_id'   => $session->id,
            'method'       => $payload->method,
            'path'         => $payload->path,
            'route_name'   => $payload->routeName,
            'route_action' => $payload->routeAction,
            'route_params' => $payload->routeParams,
            'query_params' => $payload->queryParams,
            'status_code'  => null,
            'duration_ms'  => null,
            'created_at'   => Carbon::parse($payload->capturedAt),
        ]);

        $this->repositories->sessions->touchActivity($session, pageViewDelta: 1);
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function processEvent(Payload $payload, string $name, array $eventPayload): void
    {
        if ($this->shouldDrop($payload)) {
            return;
        }

        $sessionAttrs = $this->enricher->enrich($payload);
        $session = $this->repositories->sessions->findOrCreateByUuid($payload->sessionId, $sessionAttrs);

        $this->repositories->events->create([
            'session_id' => $session->id,
            'name'       => $name,
            'payload'    => $eventPayload,
            'created_at' => Carbon::parse($payload->capturedAt),
        ]);

        $this->repositories->sessions->touchActivity($session, eventDelta: 1);
    }

    private function shouldDrop(Payload $payload): bool
    {
        if ((bool) config('tracker.privacy.drop_bots', true) && $this->botFilter->isBot($payload->userAgent)) {
            return true;
        }

        return false;
    }
}
