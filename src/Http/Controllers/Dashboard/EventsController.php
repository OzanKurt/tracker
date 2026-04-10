<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use OzanKurt\Tracker\Models\Event;

class EventsController
{
    public function __construct(
        private readonly Factory $view,
    ) {}

    public function __invoke(Request $request): View
    {
        $query = Event::query()->with('session')->orderByDesc('created_at');

        if ($name = $request->query('name')) {
            $query->where('name', (string) $name);
        }

        $events = $query->paginate(50)->withQueryString();

        return $this->view->make('tracker::pages.events', [
            'events' => $events,
            'filter' => $request->query('name'),
        ]);
    }
}
