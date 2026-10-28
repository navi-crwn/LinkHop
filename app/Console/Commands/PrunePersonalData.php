<?php

namespace App\Console\Commands;

use App\Models\AbuseReport;
use App\Models\ActivityLog;
use Hop\Bio\Models\BioClick;
use Illuminate\Console\Command;

/**
 * Retention periods promised in the privacy policy (resources/views/marketing/privacy.blade.php).
 * Link click logs are purged by clicks:purge.
 */
class PrunePersonalData extends Command
{
    public const BIO_CLICK_DAYS = 30;

    public const LOGIN_HISTORY_DAYS = 90;

    public const ACTIVITY_LOG_DAYS = 90;

    public const ABUSE_REPORT_IP_DAYS = 30;

    public const CLOSED_ABUSE_REPORT_DAYS = 180;

    protected $signature = 'privacy:prune';

    protected $description = 'Delete visitor and account logs older than the privacy policy allows';

    public function handle(): int
    {
        $counts = [
            'bio clicks' => bio_enabled() ? BioClick::where('clicked_at', '<', now()->subDays(self::BIO_CLICK_DAYS))->delete() : 0,
            'activity entries' => ActivityLog::where('created_at', '<', now()->subDays(self::ACTIVITY_LOG_DAYS))->delete(),
            'closed abuse reports' => AbuseReport::where('status', '!=', 'open')
                ->where('updated_at', '<', now()->subDays(self::CLOSED_ABUSE_REPORT_DAYS))->delete(),
            'abuse reporter IPs' => AbuseReport::whereNotNull('ip_address')
                ->where('created_at', '<', now()->subDays(self::ABUSE_REPORT_IP_DAYS))->update(['ip_address' => null]),
        ];
        foreach ($counts as $what => $n) {
            $this->info("{$what}: {$n}");
        }

        return self::SUCCESS;
    }
}
