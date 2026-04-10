<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Repositories;

use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Models\Session;

class SessionRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Session
    {
        return Session::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateByUuid(string $uuid, array $attributes): Session
    {
        $existing = Session::where('uuid', $uuid)->first();

        if ($existing !== null) {
            return $existing;
        }

        return Session::create(['uuid' => $uuid] + $attributes);
    }

    public function touchActivity(Session $session, int $pageViewDelta = 0, int $eventDelta = 0): void
    {
        $session->last_activity_at = Carbon::now();

        if ($pageViewDelta !== 0) {
            $session->page_views_count += $pageViewDelta;
        }

        if ($eventDelta !== 0) {
            $session->events_count += $eventDelta;
        }

        $session->save();
    }
}
