<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

class OverviewController
{
    public function __construct(private readonly ViewFactory $view) {}

    public function __invoke(): View
    {
        return $this->view->make('tracker::pages.overview');
    }
}
