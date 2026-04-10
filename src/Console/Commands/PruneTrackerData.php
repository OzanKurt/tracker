<?php

declare(strict_types=1);

namespace OzanKurt\Tracker\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use OzanKurt\Tracker\Models\Session;

class PruneTrackerData extends Command
{
    /** @var string */
    protected $signature = 'tracker:prune';

    /** @var string */
    protected $description = 'Delete tracker sessions older than tracker.privacy.retention_days';

    public function handle(): int
    {
        $days = (int) config('tracker.privacy.retention_days', 0);

        if ($days <= 0) {
            $this->info('Retention disabled (tracker.privacy.retention_days <= 0). Nothing to prune.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays($days);

        $deleted = 0;
        Session::where('started_at', '<', $cutoff)
            ->chunkById(500, function ($sessions) use (&$deleted): void {
                foreach ($sessions as $session) {
                    $session->pageViews()->delete();
                    $session->events()->delete();
                    $session->delete();
                    $deleted++;
                }
            });

        $this->info("Pruned {$deleted} tracker sessions older than {$days} days.");

        return self::SUCCESS;
    }
}
