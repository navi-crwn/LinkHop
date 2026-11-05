<?php

namespace App\Console\Commands;

use App\Support\GuestDigest;
use Illuminate\Console\Command;

/**
 * The daily count of new guest links by destination domain, so a kit that makes links by the hundred shows up as
 * a spike the next morning: on screen, in storage/app/sweeps/, at GET /api/hop/x/guest-digest for Imperium, and in
 * Imperium's inbox when HOP_NOTIFY_URL is set.
 */
class GuestLinkDigest extends Command
{
    protected $signature = 'links:guest-digest
        {--hours=24 : The window to report, ending now}
        {--no-push : Do not send the digest to Imperium even when HOP_NOTIFY_URL is set}';

    protected $description = 'Count the guest links of the last day by destination domain and report spikes to Imperium';

    public function handle(GuestDigest $digests): int
    {
        $digest = $digests->build(max(1, (int) $this->option('hours')));
        $path = $digests->store($digest);
        $this->line(sprintf('Guest links in the last %dh: %d (%d pending, %d cleared, %d disabled, %d flags, %d creators).',
            $digest['window']['hours'], $digest['guest_links'], $digest['pending'], $digest['cleared'], $digest['disabled'], $digest['flags'], $digest['creators']));
        if ($digest['domains'] !== []) {
            $this->table(['domain', 'links', 'pending', 'disabled', 'spike'], array_map(fn ($d) => [
                $d['domain'], $d['links'], $d['pending'], $d['disabled'], $d['spike'] ? 'YES' : '',
            ], array_slice($digest['domains'], 0, 30)));
        }
        if ($digest['spikes'] !== []) {
            $this->warn('Spikes (>= '.$digest['spike_threshold'].' new guest links on one domain): '.implode(', ', $digest['spikes']));
        }
        $this->info('Written: '.$path);
        if (! $this->option('no-push')) {
            $status = $digests->push($digest);
            $this->line($status === null ? 'Imperium push: off (HOP_NOTIFY_URL not set).' : "Imperium push: HTTP {$status}.");
        }

        return self::SUCCESS;
    }
}
