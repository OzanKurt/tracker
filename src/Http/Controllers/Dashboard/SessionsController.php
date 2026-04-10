<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use OzanKurt\Tracker\Models\Event;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

class SessionsController
{
    public function __construct(
        private readonly Factory $view,
    ) {}

    public function index(Request $request): View
    {
        $query = Session::query()->orderByDesc('last_activity_at');

        if ($country = $request->query('country')) {
            $query->where('country_code', (string) $country);
        }

        if ($device = $request->query('device')) {
            $query->where('device_kind', (string) $device);
        }

        if ($browser = $request->query('browser')) {
            $query->where('browser', (string) $browser);
        }

        $sessions = $query->paginate(25)->withQueryString();

        return $this->view->make('tracker::pages.sessions.index', [
            'sessions' => $sessions,
            'filters' => [
                'country' => $request->query('country'),
                'device' => $request->query('device'),
                'browser' => $request->query('browser'),
            ],
        ]);
    }

    public function show(string $uuid): View
    {
        $session = Session::where('uuid', $uuid)->firstOrFail();

        $pageViews = $session->pageViews()->orderBy('created_at')->get();
        $events    = $session->events()->orderBy('created_at')->get();

        $timeline = collect()
            ->merge($pageViews->map(fn ($pv) => [
                'type'  => 'page_view',
                'at'    => $pv->created_at,
                'label' => $pv->method.' '.$pv->path,
                'meta'  => $pv->route_name,
            ]))
            ->merge($events->map(fn ($ev) => [
                'type'  => 'event',
                'at'    => $ev->created_at,
                'label' => $ev->name,
                'meta'  => $ev->payload ? json_encode($ev->payload) : null,
            ]))
            ->sortBy('at')
            ->values();

        return $this->view->make('tracker::pages.sessions.show', [
            'session'  => $session,
            'timeline' => $timeline,
        ]);
    }
}
