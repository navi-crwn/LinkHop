<?php

namespace App\Console\Commands;

use App\Helpers\SocialUrlHelper;
use App\Models\AbuseFlag;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\User;
use App\Services\LinkSafety;
use App\Services\LinkService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class AbuseScan extends Command
{
    protected $signature = 'abuse:scan
        {--since=2h : Only items created/updated within this window (e.g. 30m, 2h, 7d)}
        {--full : Scan everything, ignoring --since}
        {--remote : Query Safe Browsing / URLhaus (default on for windowed scans, off for --full)}
        {--purge : Delete items disabled for abuse longer than abuse.purge_after_days (never CSAM)}
        {--dry-run : Report only, change nothing}';

    public const LAST_RUN_KEY = 'abuse:scan:last_run';

    protected $description = 'Scan links and bio pages for spam/abuse; disable offenders and suspend repeat accounts';

    private bool $dryRun = false;

    private bool $remote = false;

    /** @var array<int, array{max: int, disabled: int}> */
    private array $owners = [];

    /** @var array<string, int> */
    private array $stats = ['scanned' => 0, 'flagged' => 0, 'disabled' => 0, 'suspended' => 0, 'purged' => 0];

    /** @var array<int, string> */
    private array $notable = [];

    public function __construct(
        private readonly LinkSafety $safety,
        private readonly LinkService $links,
    ) {
        parent::__construct();
    }

    public function handle(NotificationService $notifications): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->remote = $this->option('remote') || ! $this->option('full');
        $since = $this->option('full') ? null : $this->parseWindow((string) $this->option('since'));
        $this->safety->forgetBlacklist();
        $protected = $this->protectedUserIds();

        $this->scanLinks($since, $protected);
        $this->scanBioPages($since, $protected);
        $this->scanBioLinks($since, $protected);
        $this->suspendRepeatOffenders($protected);
        if ($this->option('purge')) {
            $this->purge();
        }

        $this->table(array_keys($this->stats), [array_values($this->stats)]);
        foreach (array_slice($this->notable, 0, 50) as $line) {
            $this->line('  '.$line);
        }
        if ($this->dryRun) {
            $this->warn('Dry run: nothing was changed.');
        } else {
            // Shown on the admin Abuse page so a scheduler that never runs is visible.
            Cache::forever(self::LAST_RUN_KEY, [
                'at' => now()->toIso8601String(),
                'mode' => $this->option('full') ? 'full' : 'since '.$this->option('since'),
                'stats' => $this->stats,
            ]);
        }
        if (! $this->dryRun && $this->stats['disabled'] + $this->stats['suspended'] > 0) {
            $notifications->notifySuspiciousActivity([
                'source' => 'abuse:scan',
                'stats' => $this->stats,
                'items' => array_slice($this->notable, 0, 50),
            ]);
        }

        return self::SUCCESS;
    }

    private function scanLinks(?\DateTimeInterface $since, array $protected): void
    {
        $query = Link::query()->where('status', Link::STATUS_ACTIVE);
        $this->window($query, $since)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $protected))
            ->chunkById(500, function ($links) {
                foreach ($links as $link) {
                    $text = implode(' ', array_filter([$link->slug, $link->custom_title, $link->custom_description]));
                    $result = $this->safety->assess($link->target_url, $text, $this->remote);
                    $summary = '/'.$link->slug.' -> '.($this->safety->host($link->target_url) ?? '?');
                    $this->judge('link', $link->id, $link->user_id, $result, $summary, function () use ($link) {
                        $link->forceFill(['status' => Link::STATUS_INACTIVE])->save();
                        $this->links->forgetCache($link);
                    });
                }
            });
    }

    private function scanBioPages(?\DateTimeInterface $since, array $protected): void
    {
        $query = BioPage::query()->where('is_published', true)->whereNotIn('user_id', $protected);
        $this->window($query, $since)->chunkById(200, function ($pages) {
            foreach ($pages as $page) {
                $text = implode(' ', array_filter([$page->slug, $page->title, $page->bio, $page->seo_title, $page->seo_description]));
                $result = $this->safety->assess(null, $text);
                foreach ((array) $page->social_links as $social) {
                    $value = trim((string) ($social['value'] ?? ''));
                    $url = $value === '' ? '' : SocialUrlHelper::buildUrl((string) ($social['platform'] ?? ''), $value);
                    if ($url !== '' && $this->safety->scheme($url) !== null) {
                        $result = $this->merge($result, $this->safety->assess($url, '', $this->remote));
                    }
                }
                $this->judge('bio_page', $page->id, $page->user_id, $result, '/b/'.$page->slug, function () use ($page) {
                    $page->forceFill(['is_published' => false])->save();
                });
            }
        });
    }

    private function scanBioLinks(?\DateTimeInterface $since, array $protected): void
    {
        $query = BioLink::query()->with('bioPage:id,user_id,slug')->where('is_active', true)
            ->whereHas('bioPage', fn ($q) => $q->whereNotIn('user_id', $protected));
        $this->window($query, $since)->chunkById(500, function ($blocks) {
            foreach ($blocks as $block) {
                $url = $block->url ?: $block->embed_url;
                $text = implode(' ', array_filter([
                    $block->title, $block->description,
                    strip_tags((string) $block->content), strip_tags((string) $block->html_content),
                ]));
                $result = $this->safety->assess($url, $text, $this->remote);
                $summary = '/b/'.$block->bioPage?->slug.' block #'.$block->id.' -> '.($this->safety->host($url) ?? $block->type);
                $this->judge('bio_link', $block->id, $block->bioPage?->user_id, $result, $summary, function () use ($block) {
                    $block->forceFill(['is_active' => false])->save();
                });
            }
        });
    }

    /**
     * Record a finding and disable the subject when the score is high enough.
     */
    private function judge(string $type, int $id, ?int $userId, array $result, string $summary, callable $disable): void
    {
        $this->stats['scanned']++;
        $score = $result['score'];
        if ($score < 10) {
            return;
        }
        // An admin dismissed a previous finding for this subject: trust that decision.
        if (AbuseFlag::where('subject_type', $type)->where('subject_id', $id)->where('status', AbuseFlag::STATUS_DISMISSED)->exists()) {
            return;
        }
        $isCsam = in_array('csam', $result['signals'], true);
        // Never store full URLs for CSAM hits; the summary is slug/domain only.
        $label = ($isCsam ? '[CSAM] ' : '').$summary.' ['.implode(',', $result['signals']).'] score '.$score;
        $shouldDisable = $score >= (int) config('abuse.thresholds.disable', 60);
        if ($userId) {
            $this->owners[$userId]['max'] = max($this->owners[$userId]['max'] ?? 0, $score);
            $this->owners[$userId]['disabled'] = ($this->owners[$userId]['disabled'] ?? 0) + ($shouldDisable ? 1 : 0);
        }
        if (! $shouldDisable) {
            $alreadyFlagged = AbuseFlag::where('subject_type', $type)->where('subject_id', $id)->exists();
            if (! $alreadyFlagged) {
                $this->stats['flagged']++;
                $this->notable[] = 'REVIEW '.$label;
                $this->record($type, $id, $userId, $result, AbuseFlag::ACTION_FLAGGED, $summary);
            }

            return;
        }
        $this->stats['disabled']++;
        $this->notable[] = 'DISABLED '.$label;
        if (! $this->dryRun) {
            $disable();
        }
        $this->record($type, $id, $userId, $result, AbuseFlag::ACTION_DISABLED, $summary);
    }

    private function suspendRepeatOffenders(array $protected): void
    {
        $scoreLimit = (int) config('abuse.thresholds.suspend_user', 100);
        $countLimit = (int) config('abuse.suspend_after_disabled_items', 3);
        foreach ($this->owners as $userId => $o) {
            if (in_array($userId, $protected, true) || (($o['max'] ?? 0) < $scoreLimit && ($o['disabled'] ?? 0) < $countLimit)) {
                continue;
            }
            $user = User::find($userId);
            if (! $user || $user->isSuspended()) {
                continue;
            }
            $this->stats['suspended']++;
            $this->notable[] = "SUSPENDED user #{$user->id} {$user->email} (max score {$o['max']}, {$o['disabled']} items disabled)";
            $this->record('user', $user->id, $user->id, ['score' => $o['max'], 'signals' => []], AbuseFlag::ACTION_SUSPENDED, $user->email);
            if ($this->dryRun) {
                continue;
            }
            $user->forceFill(['status' => User::STATUS_SUSPENDED])->save();
            // A suspended spammer's remaining links stop resolving too.
            Link::where('user_id', $user->id)->where('status', Link::STATUS_ACTIVE)->get()
                ->each(function (Link $link) {
                    $link->forceFill(['status' => Link::STATUS_INACTIVE])->save();
                    $this->links->forgetCache($link);
                });
            BioPage::where('user_id', $user->id)->update(['is_published' => false]);
        }
    }

    private function purge(): void
    {
        $cutoff = now()->subDays((int) config('abuse.purge_after_days', 30));
        AbuseFlag::where('action', AbuseFlag::ACTION_DISABLED)
            ->where('status', '!=', AbuseFlag::STATUS_DISMISSED)
            ->where('created_at', '<', $cutoff)
            ->chunkById(200, function ($flags) {
                foreach ($flags as $flag) {
                    // CSAM evidence (metadata) must be retained for reporting.
                    if ($flag->isCsam()) {
                        continue;
                    }
                    $subject = match ($flag->subject_type) {
                        'link' => Link::where('id', $flag->subject_id)->where('status', Link::STATUS_INACTIVE)->first(),
                        'bio_page' => BioPage::where('id', $flag->subject_id)->where('is_published', false)->first(),
                        'bio_link' => BioLink::where('id', $flag->subject_id)->where('is_active', false)->first(),
                        default => null,
                    };
                    if (! $subject) {
                        continue;
                    }
                    $this->stats['purged']++;
                    $this->notable[] = 'PURGED '.$flag->subject_type.' #'.$flag->subject_id.' '.$flag->summary;
                    if ($this->dryRun) {
                        continue;
                    }
                    if ($subject instanceof Link) {
                        $this->links->forgetCache($subject);
                    }
                    $subject->delete();
                    $flag->update(['action' => AbuseFlag::ACTION_PURGED]);
                }
            });
    }

    private function record(string $type, int $id, ?int $userId, array $result, string $action, string $summary): void
    {
        if ($this->dryRun) {
            return;
        }
        AbuseFlag::create([
            'subject_type' => $type,
            'subject_id' => $id,
            'user_id' => $userId,
            'score' => $result['score'],
            'signals' => $result['signals'],
            'action' => $action,
            'status' => AbuseFlag::STATUS_OPEN,
            'summary' => mb_substr($summary, 0, 255),
        ]);
    }

    private function merge(array $a, array $b): array
    {
        $signals = array_values(array_unique(array_merge($a['signals'], $b['signals'])));
        $weights = config('abuse.weights', []);

        return [
            'score' => array_sum(array_map(fn ($s) => (int) ($weights[$s] ?? 0), $signals)),
            'signals' => $signals,
        ];
    }

    private function window(Builder $query, ?\DateTimeInterface $since): Builder
    {
        return $since ? $query->where($query->getModel()->getTable().'.updated_at', '>=', $since) : $query;
    }

    private function parseWindow(string $value): \DateTimeInterface
    {
        if (! preg_match('/^(\d+)\s*([mhd])$/i', trim($value), $m)) {
            return now()->subHours(2);
        }

        return match (strtolower($m[2])) {
            'm' => now()->subMinutes((int) $m[1]),
            'h' => now()->subHours((int) $m[1]),
            'd' => now()->subDays((int) $m[1]),
        };
    }

    /**
     * Superadmin content is never auto-actioned.
     *
     * @return array<int, int>
     */
    private function protectedUserIds(): array
    {
        $email = config('auth.superadmin_email');

        return $email ? User::where('email', $email)->pluck('id')->all() : [];
    }
}
