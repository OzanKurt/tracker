<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Stats;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OzanKurt\Tracker\Models\PageView;
use OzanKurt\Tracker\Models\Session;

class TrackerStats
{
    public function uniqueVisitors(Carbon $since): int
    {
        return (int) Session::where('started_at', '>=', $since)
            ->distinct('visitor_uuid')
            ->count('visitor_uuid');
    }

    /**
     * @return Collection<int, PageView>
     */
    public function topPages(Carbon $since, int $limit = 10): Collection
    {
        /** @var Collection<int, PageView> $result */
        $result = PageView::where('created_at', '>=', $since)
            ->select('path', DB::raw('COUNT(*) as views'))
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, Session>
     */
    public function topCountries(Carbon $since, int $limit = 10): Collection
    {
        /** @var Collection<int, Session> $result */
        $result = Session::where('started_at', '>=', $since)
            ->whereNotNull('country_code')
            ->select('country_code', 'country_name', DB::raw('COUNT(*) as sessions'))
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, Session>
     */
    public function topBrowsers(Carbon $since, int $limit = 10): Collection
    {
        /** @var Collection<int, Session> $result */
        $result = Session::where('started_at', '>=', $since)
            ->select('browser', DB::raw('COUNT(*) as sessions'))
            ->groupBy('browser')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, Session>
     */
    public function topDevices(Carbon $since, int $limit = 10): Collection
    {
        /** @var Collection<int, Session> $result */
        $result = Session::where('started_at', '>=', $since)
            ->select('device_kind', DB::raw('COUNT(*) as sessions'))
            ->groupBy('device_kind')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @return Collection<int, Session>
     */
    public function topReferers(Carbon $since, int $limit = 10): Collection
    {
        /** @var Collection<int, Session> $result */
        $result = Session::where('started_at', '>=', $since)
            ->whereNotNull('referer_domain')
            ->select('referer_domain', 'referer_medium', DB::raw('COUNT(*) as sessions'))
            ->groupBy('referer_domain', 'referer_medium')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @param  'hour'|'day'  $interval
     * @return Collection<int, Session>
     */
    public function sessionsOverTime(Carbon $since, string $interval = 'hour'): Collection
    {
        $expression = $this->bucketExpression(
            column: 'started_at',
            interval: $interval,
            driver: DB::getDriverName(),
        );

        /** @var Collection<int, Session> $result */
        $result = Session::where('started_at', '>=', $since)
            ->select(DB::raw($expression.' as bucket'), DB::raw('COUNT(*) as sessions'))
            ->groupBy(DB::raw($expression))
            ->orderBy(DB::raw($expression))
            ->get();

        return $result;
    }

    /**
     * @param  'hour'|'day'  $interval
     * @return Collection<int, PageView>
     */
    public function pageViewsOverTime(Carbon $since, string $interval = 'hour'): Collection
    {
        $expression = $this->bucketExpression(
            column: 'created_at',
            interval: $interval,
            driver: DB::getDriverName(),
        );

        /** @var Collection<int, PageView> $result */
        $result = PageView::where('created_at', '>=', $since)
            ->select(DB::raw($expression.' as bucket'), DB::raw('COUNT(*) as views'))
            ->groupBy(DB::raw($expression))
            ->orderBy(DB::raw($expression))
            ->get();

        return $result;
    }

    /**
     * @param  'hour'|'day'  $interval
     */
    private function bucketExpression(string $column, string $interval, string $driver): string
    {
        return match ($driver) {
            'mysql' => $interval === 'day'
                ? "DATE_FORMAT({$column}, '%Y-%m-%d')"
                : "DATE_FORMAT({$column}, '%Y-%m-%d %H:00:00')",
            'pgsql' => $interval === 'day'
                ? "TO_CHAR({$column}, 'YYYY-MM-DD')"
                : "TO_CHAR({$column}, 'YYYY-MM-DD HH24:00:00')",
            default => $interval === 'day'
                ? "strftime('%Y-%m-%d', {$column})"
                : "strftime('%Y-%m-%d %H:00:00', {$column})",
        };
    }
}
