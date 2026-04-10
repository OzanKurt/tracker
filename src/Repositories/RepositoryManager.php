<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Repositories;

class RepositoryManager
{
    public function __construct(
        public readonly SessionRepository $sessions,
        public readonly PageViewRepository $pageViews,
        public readonly EventRepository $events,
        public readonly GeoIpCacheRepository $geoIpCache,
    ) {}
}
