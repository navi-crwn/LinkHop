<?php

namespace App\Console\Commands;

use App\Models\AbuseFlag;
use App\Models\Link;
use App\Models\User;
use App\Services\LinkService;
use App\Support\EncryptedNoteLink;
use App\Support\PhishingShape;
use Carbon\Carbon;
use Hop\Safety\LinkSafety;
use Hop\Safety\Models\DomainBlacklist;
use Hop\Safety\UrlGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Finds short links that point at a phishing kit by the shape of the destination address, without fetching it:
 * a machine-made subdomain or path segment, the kit's r=/t= tracking pair, a throwaway TLD, a known kit host, the
 * blacklist, or the kit's phrases in the address (App\Support\PhishingShape, hop/safety). --fetch asks the destination
 * of weak candidates once, through UrlGuard, and matches the kit's page text.
 *
 * Dry run by default: a table on screen and a CSV under storage/app/sweeps/. --apply switches the hits off (never
 * deletes) and records an abuse flag on each; accounts are listed for the owner to act on in Imperium, never suspended here.
 */
class SweepPhishingLinks extends Command
{
    protected $signature = 'links:sweep-phishing
        {--since= : Only links created within this window (30m, 12h, 7d) or since a date (2026-11-01)}
        {--guest-only : Only links made without an account}
        {--hosts= : More destination hosts to treat as kit hosts in this run (comma separated)}
        {--fetch : Fetch the destination of weak candidates (UrlGuard-checked, pinned, no redirects) and match the kit page text}
        {--dry-run : Report only; the default}
        {--apply : Disable every hit and record an abuse flag on it}
        {--blacklist : With --apply, also add the hit domains to the domain blacklist (category phishing)}
        {--csv= : Where to write the CSV (default storage/app/sweeps/phishing-<time>.csv)}';

    protected $description = 'Find short links that point at a phishing kit by the shape of the destination; --apply disables them';

    /** What each reason adds to the score written on the flag. */
    private const WEIGHTS = ['kit_host' => 100, 'blacklisted' => 100, 'page_text' => 100, 'phishing_keywords' => 80, 'random_subdomain' => 30, 'random_path' => 20, 'tracking_params' => 20, 'risky_tld' => 5];

    private const SHAPE_SIGNALS = ['random_subdomain', 'random_path', 'tracking_params'];

    private const FETCH_MAX_BYTES = 262144;

    /** @var array<int, string> */
    private array $extraHosts = [];

    public function __construct(
        private readonly LinkSafety $safety,
        private readonly LinkService $links,
        private readonly UrlGuard $guard,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->extraHosts = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('hosts')))));
        $since = $this->since((string) $this->option('since'));
        if ($this->option('since') && $since === null) {
            $this->error('--since takes a window (30m, 12h, 7d) or a date (2026-11-01).');

            return self::INVALID;
        }
        $this->safety->forgetBlacklist();

        $rows = [];
        $query = Link::query()->orderBy('id');
        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        if ($this->option('guest-only')) {
            $query->whereNull('user_id');
        }
        $scanned = 0;
        $fetched = 0;
        $query->chunkById(500, function ($links) use (&$rows, &$scanned, &$fetched) {
            foreach ($links as $link) {
                $scanned++;
                $row = $this->examine($link);
                if (! $row['hit'] && $this->option('fetch') && $row['reasons'] !== []) {
                    $fetched++;
                    if ($this->pageMatches($link->target_url)) {
                        $row['reasons'][] = 'page_text';
                        $row['hit'] = true;
                        $row['action'] = $link->status === Link::STATUS_ACTIVE ? 'would_disable' : $row['action'];
                    }
                }
                if ($row['reasons'] !== []) {
                    $row['score'] = $this->score($row['reasons']);
                    $rows[] = $row;
                }
            }
        });
        $hits = array_values(array_filter($rows, fn ($r) => $r['hit']));
        $this->line(sprintf('Scanned %d link(s)%s: %d hit(s), %d weak candidate(s)%s.', $scanned, $since ? ' since '.$since->toDateTimeString() : '',
            count($hits), count($rows) - count($hits), $this->option('fetch') ? ", {$fetched} fetched" : ''));

        if ($apply) {
            $this->applyTo($hits);
        }
        $this->printGroups($hits);
        $csv = $this->writeCsv($rows, $hits, $apply);
        $this->info('CSV: '.$csv);
        if (! $apply) {
            $this->warn('Dry run: nothing was changed. Add --apply to disable the hits.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{link: Link, hit: bool, reasons: list<string>, host: string, domain: string, creator: string, score: int, action: string}
     */
    private function examine(Link $link): array
    {
        $url = (string) EncryptedNoteLink::withoutFragment($link->target_url);
        $shape = PhishingShape::assess($url);
        $host = $shape['host'] ?? '';
        $reasons = array_values(array_intersect($shape['signals'], [...self::SHAPE_SIGNALS, 'risky_tld']));
        $hit = $shape['hit'];
        if ($host !== '' && (in_array('kit_host', $shape['signals'], true) || PhishingShape::isKitHost($host, $this->extraHosts))) {
            $reasons[] = 'kit_host';
            $hit = true;
        }
        if ($host !== '' && $this->safety->blacklistCategory($host) !== false) {
            $reasons[] = 'blacklisted';
            $hit = true;
        }
        $text = implode(' ', array_filter([$link->slug, $link->custom_title, $link->custom_description]));
        if (in_array('phishing', $this->safety->assess($url, $text, false)['signals'], true)) {
            $reasons[] = 'phishing_keywords';
            $hit = true;
        }
        // A lone risky TLD is no reason to list a link.
        if ($reasons === ['risky_tld']) {
            $reasons = [];
        }

        return [
            'link' => $link, 'hit' => $hit, 'reasons' => array_values(array_unique($reasons)), 'host' => $host,
            'domain' => $shape['domain'] ?? $host, 'creator' => $this->creator($link), 'score' => 0,
            'action' => $link->status === Link::STATUS_ACTIVE ? ($hit ? 'would_disable' : 'review') : 'already_'.$link->status,
        ];
    }

    private function creator(Link $link): string
    {
        if ($link->user_id) {
            return 'user:'.$link->user_id;
        }

        return $link->creator_hash ? 'guest:'.substr($link->creator_hash, 0, 12) : 'guest:unknown';
    }

    /** @param  list<string>  $reasons */
    private function score(array $reasons): int
    {
        return (int) array_sum(array_map(fn ($r) => self::WEIGHTS[$r] ?? 0, $reasons));
    }

    /** @param  list<array<string, mixed>>  $hits */
    private function applyTo(array &$hits): void
    {
        $disabled = 0;
        $domains = [];
        foreach ($hits as &$row) {
            /** @var Link $link */
            $link = $row['link'];
            $domains[$row['domain']] = true;
            if ($link->status !== Link::STATUS_ACTIVE) {
                continue;
            }
            // An admin restored this link after an earlier finding: that decision stands.
            if (AbuseFlag::where('subject_type', 'link')->where('subject_id', $link->id)->where('status', AbuseFlag::STATUS_DISMISSED)->exists()) {
                $row['action'] = 'skipped_dismissed';

                continue;
            }
            $link->forceFill(['status' => Link::STATUS_INACTIVE, 'safety_status' => $link->safety_status ? Link::SAFETY_FLAGGED : null])->save();
            $this->links->forgetCache($link);
            AbuseFlag::create([
                'subject_type' => 'link', 'subject_id' => $link->id, 'user_id' => $link->user_id,
                'score' => min(100, $row['score']), 'signals' => $row['reasons'],
                'action' => AbuseFlag::ACTION_DISABLED, 'status' => AbuseFlag::STATUS_OPEN,
                'summary' => mb_substr('/'.$link->slug.' -> '.$row['host'].' [links:sweep-phishing]', 0, 255),
            ]);
            $row['action'] = 'disabled';
            $disabled++;
        }
        unset($row);
        $this->info("Disabled {$disabled} link(s); each has an abuse flag (action disabled, status open).");
        if ($this->option('blacklist') && $domains !== []) {
            $added = 0;
            foreach (array_keys($domains) as $domain) {
                if ($domain === '' || filter_var($domain, FILTER_VALIDATE_IP)) {
                    continue;
                }
                foreach ([[$domain, 'exact'], ['*.'.$domain, 'wildcard']] as [$pattern, $type]) {
                    $added += DomainBlacklist::firstOrCreate(['domain' => $pattern, 'match_type' => $type],
                        ['category' => 'phishing', 'notes' => 'links:sweep-phishing '.now()->toDateString()])->wasRecentlyCreated ? 1 : 0;
                }
            }
            $this->safety->forgetBlacklist();
            $this->info("Blacklist: {$added} row(s) added (phishing).");
        }
    }

    /** @param  list<array<string, mixed>>  $hits */
    private function printGroups(array $hits): void
    {
        if ($hits === []) {
            $this->info('No hits.');

            return;
        }
        $byDomain = [];
        $byCreator = [];
        foreach ($hits as $row) {
            /** @var Link $link */
            $link = $row['link'];
            $d = &$byDomain[$row['domain']];
            $d['links'] = ($d['links'] ?? 0) + 1;
            $d['active'] = ($d['active'] ?? 0) + ($link->status === Link::STATUS_ACTIVE ? 1 : 0);
            $d['creators'][$row['creator']] = true;
            $d['slugs'][] = $link->slug;
            unset($d);
            $c = &$byCreator[$row['creator']];
            $c['links'] = ($c['links'] ?? 0) + 1;
            $c['domains'][$row['domain']] = true;
            $c['first'] = isset($c['first']) ? min($c['first'], $link->created_at) : $link->created_at;
            $c['last'] = isset($c['last']) ? max($c['last'], $link->created_at) : $link->created_at;
            unset($c);
        }
        uasort($byDomain, fn ($a, $b) => $b['links'] <=> $a['links']);
        uasort($byCreator, fn ($a, $b) => $b['links'] <=> $a['links']);
        $this->line('');
        $this->line('Hits by destination domain:');
        $this->table(['domain', 'links', 'active', 'creators', 'sample slugs'], array_map(fn ($domain, $d) => [
            $domain, $d['links'], $d['active'], count($d['creators']), implode(' ', array_slice($d['slugs'], 0, 5)).(count($d['slugs']) > 5 ? ' …' : ''),
        ], array_keys($byDomain), $byDomain));
        $this->line('Hits by creator (user:<id>, guest:<hash prefix>, or guest:unknown for links made before the creator was stored):');
        $this->table(['creator', 'links', 'domains', 'first', 'last'], array_map(fn ($creator, $c) => [
            $creator, $c['links'], count($c['domains']), $c['first']?->toDateTimeString(), $c['last']?->toDateTimeString(),
        ], array_keys($byCreator), $byCreator));
        $accounts = array_filter(array_keys($byCreator), fn ($c) => str_starts_with($c, 'user:'));
        if ($accounts !== []) {
            $this->line('Accounts behind hits (nothing is suspended here; act in Imperium):');
            foreach ($accounts as $creator) {
                $user = User::find((int) substr($creator, 5));
                $this->line(sprintf('  user #%d %s (%d link(s))', (int) substr($creator, 5), $user?->email ?? '(deleted)', $byCreator[$creator]['links']));
            }
        } else {
            $this->line('No accounts behind the hits: all guests.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $hits
     */
    private function writeCsv(array $rows, array $hits, bool $applied): string
    {
        // applyTo() updated the action of each hit; carry it over by link id.
        $actions = [];
        foreach ($hits as $hit) {
            $actions[$hit['link']->id] = $hit['action'];
        }
        $path = (string) ($this->option('csv') ?: storage_path('app/sweeps/phishing-'.now()->format('Ymd-His').'.csv'));
        File::ensureDirectoryExists(dirname($path));
        $out = fopen($path, 'w');
        fputcsv($out, ['link_id', 'slug', 'short_url', 'status', 'safety_status', 'creator', 'user_id', 'created_at', 'host', 'domain', 'target_url', 'hit', 'reasons', 'score', 'action']);
        foreach ($rows as $row) {
            /** @var Link $link */
            $link = $row['link'];
            $action = $actions[$link->id] ?? $row['action'];
            fputcsv($out, [
                $link->id, $link->slug, $link->short_url, $link->status, $link->safety_status, $row['creator'], $link->user_id,
                $link->created_at?->toIso8601String(), $row['host'], $row['domain'], EncryptedNoteLink::withoutFragment($link->target_url),
                $row['hit'] ? 'yes' : 'no', implode(' ', $row['reasons']), $row['score'],
                $applied ? $action : str_replace('would_disable', 'would_disable (dry run)', $action),
            ]);
        }
        fclose($out);

        return $path;
    }

    /**
     * Fetches the destination once, connected to exactly the addresses UrlGuard checked, without following redirects
     * (a hop is checked and fetched again, three at most), and asks whether the page reads like the kit.
     */
    private function pageMatches(string $url): bool
    {
        $url = (string) EncryptedNoteLink::withoutFragment($url);
        for ($hop = 0; $hop < 3; $hop++) {
            $target = $this->guard->resolve($url);
            if ($target === null) {
                return false;
            }
            try {
                $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (HEL.ink-Sweep/1.0; +'.public_url('').')', 'Accept' => 'text/html'])
                    ->timeout(6)->connectTimeout(3)
                    ->withOptions(['allow_redirects' => false, 'curl' => [
                        CURLOPT_RESOLVE => $this->guard->curlResolve($target),
                        CURLOPT_NOPROGRESS => false,
                        CURLOPT_PROGRESSFUNCTION => fn ($ch, $total, $received) => $received > self::FETCH_MAX_BYTES ? 1 : 0,
                    ]])
                    ->get($url);
            } catch (\Throwable) {
                return false;
            }
            if ($response->redirect() && ($location = $response->header('Location')) !== '') {
                $url = preg_match('#^https?://#i', $location) ? $location : $target['scheme'].'://'.$target['host'].'/'.ltrim($location, '/');

                continue;
            }

            return $response->successful() && PhishingShape::pageMatches(substr($response->body(), 0, self::FETCH_MAX_BYTES));
        }

        return false;
    }

    private function since(string $value): ?\DateTimeInterface
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d+)\s*([mhd])$/i', $value, $m)) {
            return match (strtolower($m[2])) {
                'm' => now()->subMinutes((int) $m[1]),
                'h' => now()->subHours((int) $m[1]),
                'd' => now()->subDays((int) $m[1]),
            };
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
