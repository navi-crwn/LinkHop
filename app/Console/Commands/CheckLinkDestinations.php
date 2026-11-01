<?php

namespace App\Console\Commands;

use App\Models\Link;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\DestinationChecker;
use Illuminate\Console\Command;

/**
 * The destination broken-link monitor: asks where live links point, a bounded number per run, the one checked
 * longest ago first. The state lives on the link (last_checked_at, last_status, check_failures, broken_since);
 * the owner is emailed once when a link turns broken, and again only after it has recovered and broken anew.
 */
class CheckLinkDestinations extends Command
{
    /** Links to one host per run, so a person with a thousand links to one site cannot hammer it. */
    private const PER_HOST = 5;

    protected $signature = 'links:check-destinations {--limit= : Links to check in this run (default: shortener.monitor.batch)}';

    protected $description = 'Check that the destinations of live links still answer, and tell owners when one stops';

    public function handle(DestinationChecker $checker, NotificationService $notifications): int
    {
        $cfg = config('shortener.monitor');
        if (! $cfg['enabled']) {
            $this->info('Destination monitor is switched off (LINK_MONITOR_ENABLED).');

            return self::SUCCESS;
        }
        $limit = max(1, (int) ($this->option('limit') ?: $cfg['batch']));
        $interval = now()->subHours($cfg['interval_hours']);
        $retry = now()->subHours($cfg['retry_hours']);

        // Live = active, started, not expired, not out of clicks. Links of guests have no one to tell and are skipped.
        $candidates = Link::query()->live()
            ->whereNotNull('links.user_id')
            ->where('links.target_url', 'like', 'http%')
            ->whereHas('user', fn ($u) => $u->where('monitor_destinations', true)
                ->where(fn ($s) => $s->whereNull('status')->orWhere('status', '!=', User::STATUS_SUSPENDED)))
            ->where(fn ($q) => $q->whereNull('links.last_checked_at')
                // Fine, or already flagged: look again once a day. Failing but not yet flagged: confirm sooner.
                ->orWhere(fn ($d) => $d->where(fn ($ok) => $ok->where('links.check_failures', 0)->orWhereNotNull('links.broken_since'))->where('links.last_checked_at', '<=', $interval))
                ->orWhere(fn ($d) => $d->where('links.check_failures', '>', 0)->whereNull('links.broken_since')->where('links.last_checked_at', '<=', $retry)))
            ->with('user')
            ->orderBy('links.last_checked_at')->orderBy('links.id')
            ->limit($limit * 3)
            ->get();

        $checked = $newlyBroken = 0;
        $perHost = [];
        foreach ($candidates as $link) {
            if ($checked >= $limit) {
                break;
            }
            $host = strtolower((string) parse_url($link->target_url, PHP_URL_HOST));
            if (($perHost[$host] = ($perHost[$host] ?? 0) + 1) > self::PER_HOST) {
                continue;
            }
            $checked++;
            $result = $checker->check($link->target_url);
            $update = ['last_checked_at' => now()];
            $flag = false;
            if ($result['state'] === DestinationChecker::OK) {
                $update += ['last_status' => $result['status'], 'check_failures' => 0, 'broken_since' => null];
            } elseif ($result['state'] === DestinationChecker::BROKEN) {
                $failures = min(255, $link->check_failures + 1);
                $update += ['last_status' => $result['status'], 'check_failures' => $failures];
                $flag = $failures >= (int) $cfg['failures'] && $link->broken_since === null;
            }
            // Only if the destination is still the one we asked: an edit in the meantime resets the monitor.
            $scope = Link::whereKey($link->id)->where('target_url', $link->target_url);
            if ($flag) {
                // The update that wins the "not flagged yet" race is the one that sends the email.
                $won = (clone $scope)->whereNull('broken_since')->update($update + ['broken_since' => now()]) === 1;
                if ($won) {
                    $newlyBroken++;
                    $notifications->notifyLinkBroken($link->fresh(['user']), $result['status']);
                }
            } else {
                $scope->update($update);
            }
        }
        $this->info("Checked {$checked} destinations, {$newlyBroken} newly broken.");

        return self::SUCCESS;
    }
}
