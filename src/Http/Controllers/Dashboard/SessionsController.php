<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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
}
