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
 * copy, switches the link off and leaves a line in the owner's activity log saying why. The same address anywhere else
 * a person can write (text, rich text, CSS, JSON on links, bio pages and reports) loses its key too; a link that only
 * held one there stays live. A key it cannot cut out is reported by row. It sends nobody anything.
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
        // blocks of a bio page (the hop/bio package): the same address typed into a block
        ['bio_links', 'url'],
        ['bio_links', 'embed_url'],
        // everything else a person can write into: text, rich text, CSS, JSON. Links stay live, the key just goes.
        ['abuse_reports', 'reason'],
        ['links', 'custom_title'],
        ['links', 'custom_description'],
        ['links', 'custom_image_url'],
        ['links', 'qr_logo_url'],
        ['links', 'warning_reason'],
        ['comments', 'body'],
        ['folders', 'name'],
        ['tags', 'name'],
        ['webhook_endpoints', 'url'],
        ['bio_links', 'title'],
        ['bio_links', 'icon'],
        ['bio_links', 'thumbnail_url'],
        ['bio_links', 'content'],
        ['bio_links', 'description'],
        ['bio_links', 'brand'],
        ['bio_links', 'settings'],
        ['bio_links', 'custom_icon'],
        ['bio_links', 'countdown_label'],
        ['bio_links', 'map_address'],
        ['bio_links', 'vcard_name'],
        ['bio_links', 'vcard_phone'],
        ['bio_links', 'vcard_email'],
        ['bio_links', 'vcard_company'],
        ['bio_links', 'faq_items'],
        ['bio_links', 'html_content'],
        ['bio_pages', 'title'],
        ['bio_pages', 'bio'],
        ['bio_pages', 'avatar_url'],
        ['bio_pages', 'social_links'],
        ['bio_pages', 'socials'],
        ['bio_pages', 'seo_title'],
        ['bio_pages', 'seo_description'],
        ['bio_pages', 'custom_css'],
        ['bio_pages', 'background_value'],
        ['bio_pages', 'background_image'],
        ['bio_pages', 'qr_settings'],
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
                    // One transaction per link: the cut, the switch-off and the line in the owner's log go together or not at all.
                    DB::transaction(function () use ($link, $fixed) {
                        // saveQuietly: no webhook event and no other side effect; the redirect cache is dropped by hand.
                        $link->forceFill($fixed + ['status' => Link::STATUS_INACTIVE])->saveQuietly();
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
                    });
                    $service->forgetCacheBySlug($link->slug);
                    if ($wasLive) {
                        $disabled++;
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
            $stuck = [];
            // "#" as such, as %23, \23 or &#35;, or as &num;
            DB::table($table)->where(fn ($q) => $q->where($column, 'like', '%#%')->orWhere($column, 'like', '%23%')->orWhere($column, 'like', '%&num;%'))
                ->orderBy('id')->chunkById(500, function ($chunk) use ($table, $column, $apply, $notes, $service, &$rows, &$stuck) {
                    DB::transaction(function () use ($chunk, $table, $column, $apply, $notes, $service, &$rows, &$stuck) {
                        foreach ($chunk as $row) {
                            $clean = $notes->scrubAll((string) $row->{$column});
                            if ($notes->holdsKey($clean)) {
                                $stuck[] = $row->id;
                            }
                            if ($clean === (string) $row->{$column}) {
                                continue;
                            }
                            $rows++;
                            if ($apply) {
                                DB::table($table)->where('id', $row->id)->update([$column => $clean]);
                                if ($table === 'links') {
                                    $service->forgetCacheBySlug($row->slug);
                                }
                            }
                        }
                    });
                });
            $this->line(sprintf('%s.%s rows %s: %d', $table, $column, $verb, $rows));
            if ($stuck !== []) {
                $this->warn(sprintf('%s.%s: %d rows still hold a key that could not be cut out (ids: %s). Edit them by hand.', $table, $column, count($stuck), implode(', ', $stuck)));
            }
        }

        $this->scrubSessions($apply, $verb);
        $this->dropCachedCopies($apply, $notes);

        foreach (self::QUEUES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $held = 0;
            DB::table($table)->where('payload', 'like', '%#%')->orderBy('id')->chunkById(500, function ($chunk) use ($notes, &$held) {
                foreach ($chunk as $row) {
                    $held += $notes->holdsKey((string) $row->payload) ? 1 : 0;
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

    /**
     * The database session handler keeps base64(serialize(data)), so the old input of a refused form can hold a key
     * where a LIKE cannot see it. The data is read back, cleaned string by string and written again.
     */
    private function scrubSessions(bool $apply, string $verb): void
    {
        if (! Schema::hasTable('sessions') || ! Schema::hasColumn('sessions', 'payload')) {
            return;
        }
        $rows = 0;
        DB::table('sessions')->orderBy('id')->chunkById(500, function ($chunk) use ($apply, &$rows) {
            foreach ($chunk as $row) {
                $raw = base64_decode((string) $row->payload, true);
                if ($raw === false || ! str_contains($raw, '#')) {
                    continue;
                }
                $data = @unserialize($raw, ['allowed_classes' => false]);
                if (! is_array($data)) {
                    continue;
                }
                $clean = EncryptedNoteLink::scrubInput($data);
                if ($clean === $data) {
                    continue;
                }
                $rows++;
                if ($apply) {
                    DB::table('sessions')->where('id', $row->id)->update(['payload' => base64_encode(serialize($clean))]);
                }
            }
        }, 'id');
        $this->line(sprintf('sessions.payload rows %s: %d', $verb, $rows));
    }

    /** Cached redirects and CSV previews are only copies: an entry that holds a key is deleted, not edited. */
    private function dropCachedCopies(bool $apply, EncryptedNoteLink $notes): void
    {
        $table = (string) config('cache.stores.database.table', 'cache');
        if (config('cache.default') !== 'database' || ! Schema::hasTable($table)) {
            return;
        }
        $drop = [];
        DB::table($table)->where(fn ($q) => $q->where('key', 'like', '%slug:%')->orWhere('key', 'like', '%linkimport:%'))->orderBy('key')->get(['key', 'value'])->each(function ($row) use ($notes, &$drop) {
            $value = (string) $row->value;
            $plain = base64_decode($value, true);
            foreach ([$value, $plain === false ? '' : $plain] as $text) {
                if ($notes->holdsKey($text)) {
                    $drop[] = $row->key;

                    return;
                }
            }
        });
        if ($apply && $drop !== []) {
            DB::table($table)->whereIn('key', $drop)->delete();
        }
        $this->line(sprintf('cache entries %s: %d', $apply ? 'deleted' : 'would be deleted', count($drop)));
    }

    /** The value with the secret parameters cut out of every NoteHop address that carries one; the same value when none does. */
    private function clean(mixed $value, EncryptedNoteLink $notes): mixed
    {
        if (is_string($value)) {
            return $notes->matches($value) ? EncryptedNoteLink::withoutSecrets($value) : $value;
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->clean($v, $notes), $value);
        }

        return $value;
    }
}
