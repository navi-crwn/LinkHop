<?php

namespace App\Console\Commands;

use App\Mail\ShortLinkMigrationNotice;
use App\Models\User;
use App\Support\ShortLinkMove;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every account that owns short links the list of those links in the
 * new hel.ink/l/{slug} form. Run by hand: on day 0 of the move and, with
 * --reminder, on day 23 (a week before shortener.legacy_slug_until).
 */
class AnnounceLinkMigration extends Command
{
    protected $signature = 'links:announce-migration
        {--reminder : Word the mail as the reminder sent a week before the old form stops}
        {--user= : Only this account (id or email), to test the mail}
        {--dry-run : Count and list the recipients, send nothing}';

    protected $description = 'Email every link owner their short links in the new /l/ form (day 0 and, with --reminder, day 23)';

    public function handle(): int
    {
        $until = ShortLinkMove::untilLabel();
        if ($until === '') {
            $this->error('shortener.legacy_slug_until (LEGACY_SLUG_UNTIL) is not set.');

            return self::FAILURE;
        }
        $query = User::query()->whereHas('links')->orderBy('id');
        if ($who = $this->option('user')) {
            $query->where(is_numeric($who) ? 'id' : 'email', $who);
        }
        $owners = (clone $query)->count();
        $this->info(($this->option('dry-run') ? 'Would mail ' : 'Mailing ')."{$owners} owners of short links; old form ends {$until}".($this->option('reminder') ? ' (reminder)' : '').'.');
        $sent = 0;
        $query->chunkById(100, function ($users) use (&$sent) {
            foreach ($users as $user) {
                $links = $user->links()->orderBy('created_at')->get(['id', 'slug', 'target_url', 'created_at']);
                $this->line("  {$user->email}: {$links->count()} links");
                if (! $this->option('dry-run')) {
                    Mail::to($user->email, $user->name)->queue(new ShortLinkMigrationNotice($user, $links, (bool) $this->option('reminder')));
                    $sent++;
                }
            }
        });
        $this->info($this->option('dry-run') ? 'Dry run, nothing sent.' : "{$sent} mails queued (php artisan queue:work sends them).");

        return self::SUCCESS;
    }
}
