<?php

namespace App\Console\Commands;

use App\Models\GuestLinkLog;
use Illuminate\Console\Command;

/**
 * Delete guest link records older than guest_policy.retention_days. The log exists to count a cap across days and to
 * spot a burst, and neither needs more than two weeks.
 */
class PruneGuestLinkLog extends Command
{
    protected $signature = 'links:prune-guest-log {--dry-run : Only count what would be deleted}';

    protected $description = 'Delete guest link records older than guest_policy.retention_days (config/shortener.php)';

    public function handle(): int
    {
        $days = (int) config('shortener.guest_policy.retention_days', 14);
        $cutoff = now()->subDays(max(1, $days));
        $query = GuestLinkLog::query()->where('created_at', '<', $cutoff);
        if ($this->option('dry-run')) {
            $this->info("Would delete {$query->count()} guest link records older than {$days} days.");

            return self::SUCCESS;
        }
        $deleted = 0;
        $query->chunkById(1000, function ($rows) use (&$deleted) {
            $deleted += GuestLinkLog::whereIn('id', $rows->pluck('id'))->delete();
        });
        $this->info("Deleted {$deleted} guest link records older than {$days} days.");

        return self::SUCCESS;
    }
}
