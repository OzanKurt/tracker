<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Repositories;

use OzanKurt\Tracker\Models\PageView;

class PageViewRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PageView
    {
        return PageView::create($attributes);
    }
}
