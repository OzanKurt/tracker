<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Repositories;

use OzanKurt\Tracker\Models\Event;

class EventRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Event
    {
        return Event::create($attributes);
    }
}
