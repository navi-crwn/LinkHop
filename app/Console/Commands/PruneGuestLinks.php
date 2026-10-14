<?php

namespace App\Console\Commands;

use App\Models\Link;
use App\Services\LinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneGuestLinks extends Command
{
    protected $signature = 'links:prune-guest {--dry-run : Only count what would be deleted}';

    protected $description = 'Delete anonymous links idle for 14 days or older than 30 days (config/shortener.php)';

    public function handle(LinkService $links): int
    {
        $count = 0;
        Link::guestExpired()->chunkById(500, function ($chunk) use ($links, &$count) {
            foreach ($chunk as $link) {
                $count++;
                if ($this->option('dry-run')) {
                    continue;
                }
                $links->forgetCache($link);
                if ($link->qr_code_path) {
                    Storage::disk('public')->delete($link->qr_code_path);
                }
                $link->delete();
            }
        });
        $this->info(($this->option('dry-run') ? 'Would delete' : 'Deleted')." {$count} expired guest links.");

        return self::SUCCESS;
    }
}
