<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;
use OzanKurt\Tracker\Stats\TrackerStats;

class OverviewController
{
    public function __construct(
        private readonly Factory $view,
        private readonly TrackerStats $stats,
    ) {}

    public function __invoke(): View
    {
        $since = Carbon::now()->subDay();

        return $this->view->make('tracker::pages.overview', [
            'uniqueVisitors' => $this->stats->uniqueVisitors($since),
            'sessionsCount' => Session::where('started_at', '>=', $since)->count(),
            'pageViewsCount' => PageView::where('created_at', '>=', $since)->count(),
            'onlineCount' => Session::where('last_activity_at', '>=', Carbon::now()->subMinutes(3))->count(),
            'topPages' => $this->stats->topPages($since, 5),
            'topCountries' => $this->stats->topCountries($since, 5),
            'topBrowsers' => $this->stats->topBrowsers($since, 5),
            'sessionsOverTime' => $this->stats->sessionsOverTime($since, 'hour'),
        ]);
    }
}
