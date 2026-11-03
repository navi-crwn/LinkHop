<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Link;
use App\Services\LinkService;
use App\Support\EncryptedNoteLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finds links that were saved with the key of an encrypted NoteHop note in the address (https://n.hel.ink/ID#k=KEY),
 * from before LinkHop refused them. By default it only counts. With --apply it cuts the fragment out of every stored
 * copy, switches the link off and leaves a line in the owner's activity log saying why. It sends nobody anything.
 *
 * It prints counts and link ids. Never an address, never a key.
 */
class ScrubNoteKeys extends Command
{
    protected $signature = 'links:scrub-note-keys {--apply : Cut the keys out and disable the links (without it, nothing is changed)}';

    protected $description = 'Find links that store the key of an encrypted NoteHop note, and with --apply remove the key and disable them';

    /** Other places the full address can end up as text: [table, column]. Fragments are cut out of these in place. */
    private const COPIES = [
        ['activity_logs', 'description'],
        ['webhook_deliveries', 'payload'],
        ['hop_audit', 'params'],
        ['api_idempotency_keys', 'response_body'],
        ['abuse_reports', 'url'],
    ];

    /** Queue tables hold serialized jobs, whose lengths a cut would break: counted, never edited. */
    private const QUEUES = ['jobs', 'failed_jobs'];

    public function handle(EncryptedNoteLink $notes, LinkService $service): int
    {
        $apply = (bool) $this->option('apply');
        $this->line($apply ? 'Applying: keys are cut out and the links are disabled.' : 'Dry run: nothing is changed. Run with --apply to act.');

        $ids = [];
        $disabled = 0;
        Link::query()->where(fn ($q) => $q->where('target_url', 'like', '%#%')->orWhere('fallback_url', 'like', '%#%')
            ->orWhere('routing_rules', 'like', '%#%')->orWhere('ab_variants', 'like', '%#%')->orWhere('app_links', 'like', '%#%'))
            ->chunkById(500, function ($links) use ($notes, $service, $apply, &$ids, &$disabled) {
                foreach ($links as $link) {
                    $fixed = [];
                    foreach (['target_url', 'fallback_url', 'routing_rules', 'ab_variants', 'app_links'] as $column) {
                        $value = $link->{$column};
                        $clean = $this->clean($value, $notes);
                        if ($clean !== $value) {
                            $fixed[$column] = $clean;
                        }
                    }
                    if ($fixed === []) {
                        continue;
                    }
                    $ids[] = $link->id;
                    if (! $apply) {
                        continue;
                    }
                    $wasLive = $link->status === Link::STATUS_ACTIVE;
                    // saveQuietly: no webhook event and no other side effect; the redirect cache is dropped by hand.
                    $link->forceFill($fixed + ['status' => Link::STATUS_INACTIVE])->saveQuietly();
                    $service->forgetCacheBySlug($link->slug);
                    if ($wasLive) {
                        $disabled++;
                    }
                    if ($link->user_id !== null) {
                        ActivityLog::create([
                            'user_id' => $link->user_id,
                            'action' => 'disabled',
                            'model' => 'Link',
                            'model_id' => $link->id,
                            'description' => "Disabled link {$link->slug}: it pointed at an encrypted NoteHop note with its key in the address. LinkHop no longer keeps that key, so the link was switched off.",
                            'ip_address' => null,
                            'user_agent' => 'links:scrub-note-keys',
                        ]);
                    }
                }
            });

        $verb = $apply ? 'cleaned' : 'would be cleaned';
        $this->line(sprintf('Links %s: %d%s', $verb, count($ids), $ids ? ' (ids: '.implode(', ', $ids).')' : ''));
        if ($apply) {
            $this->line("Links switched off now: {$disabled} (the others were already off).");
        }

        foreach (self::COPIES as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $rows = 0;
            DB::table($table)->where($column, 'like', '%#%')->orderBy('id')->chunkById(500, function ($chunk) use ($table, $column, $apply, &$rows) {
                foreach ($chunk as $row) {
                    $clean = EncryptedNoteLink::scrubText((string) $row->{$column});
                    if ($clean === (string) $row->{$column}) {
                        continue;
                    }
                    $rows++;
                    if ($apply) {
                        DB::table($table)->where('id', $row->id)->update([$column => $clean]);
                    }
                }
            });
            $this->line(sprintf('%s.%s rows %s: %d', $table, $column, $verb, $rows));
        }

        foreach (self::QUEUES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $held = 0;
            DB::table($table)->where('payload', 'like', '%#%')->orderBy('id')->chunkById(500, function ($chunk) use (&$held) {
                foreach ($chunk as $row) {
                    $held += EncryptedNoteLink::scrubText((string) $row->payload) !== (string) $row->payload ? 1 : 0;
                }
            });
            if ($held > 0) {
                $this->warn("{$table}: {$held} entries hold a key and were left alone. Let the queue run them out, or clear them.");
            }
        }

        if (! $apply && $ids) {
            $this->warn('Run again with --apply after taking a backup.');
        }
        $this->line('Database backups taken before this still hold the keys. Expire them on your normal schedule.');

        return self::SUCCESS;
    }

    /** The value with the fragment cut out of every address that carries a note key; the same value when none does. */
    private function clean(mixed $value, EncryptedNoteLink $notes): mixed
    {
        if (is_string($value)) {
            return $notes->matches($value) ? EncryptedNoteLink::withoutFragment($value) : $value;
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->clean($v, $notes), $value);
        }

        return $value;
    }
}
