<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use OzanKurt\Tracker\Models\PageView;

class PageViewsController
{
    public function __construct(
        private readonly Factory $view,
    ) {}

    public function __invoke(Request $request): View
    {
        $query = PageView::query()->with('session')->orderByDesc('created_at');

        if ($path = $request->query('path')) {
            // Escape LIKE wildcards so a filter of "%admin%_" can't trigger a
            // full-table scan or leak rows the operator didn't intend to match.
            // Backslash is declared as the escape char so SQLite honours it —
            // MySQL and Postgres treat it as escape by default but the clause
            // makes the behaviour explicit across drivers.
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $path);
            $query->whereRaw('path LIKE ? ESCAPE ?', ['%'.$escaped.'%', '\\']);
        }

        if ($route = $request->query('route')) {
            $query->where('route_name', (string) $route);
        }

        $pageViews = $query->paginate(50)->withQueryString();

        return $this->view->make('tracker::pages.page-views', [
            'pageViews' => $pageViews,
            'filters' => [
                'path' => $request->query('path'),
                'route' => $request->query('route'),
            ],
        ]);
    }
}
