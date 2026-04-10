<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use OzanKurt\Tracker\Models\Session;

class UsersController
{
    public function __construct(
        private readonly Factory $view,
    ) {}

    public function show(int|string $id): View
    {
        $sessions = Session::where('user_id', $id)
            ->orderByDesc('last_activity_at')
            ->paginate(25);

        return $this->view->make('tracker::pages.users.show', [
            'userId'   => $id,
            'sessions' => $sessions,
        ]);
    }
}
