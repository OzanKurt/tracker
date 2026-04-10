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
            $query->where('path', 'like', '%'.$path.'%');
        }

        if ($route = $request->query('route')) {
            $query->where('route_name', (string) $route);
        }

        $pageViews = $query->paginate(50)->withQueryString();

        return $this->view->make('tracker::pages.page-views', [
            'pageViews' => $pageViews,
            'filters'   => [
                'path'  => $request->query('path'),
                'route' => $request->query('route'),
            ],
        ]);
    }
}
