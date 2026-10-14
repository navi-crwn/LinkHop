<?php

namespace App\Services;

use App\Console\Commands\UpdateThreatFeeds;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether a destination URL (plus its surrounding text) is safe to
 * host. Shared by request validation (creation time) and `abuse:scan`.
 */
class LinkSafety
{
    public const WEB_SCHEMES = ['http', 'https'];

    public const BIO_SCHEMES = ['http', 'https', 'mailto', 'tel', 'sms'];

    private const DANGEROUS_SCHEMES = ['javascript', 'data', 'vbscript', 'file', 'blob'];

    /** @var array{exact: array<string, string|null>, wildcard: array<int, array{0: string, 1: string|null}>}|null */
    private ?array $blacklist = null;

    /** @var array<string, array<string, int>>|null */
    private ?array $feeds = null;

    /**
     * Validation helper: returns a user-facing error, or null when allowed.
     *
     * @param  array<int, string>  $schemes
     */
    public function rejectionReason(?string $url, string $text = '', array $schemes = self::WEB_SCHEMES): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $scheme = $this->scheme($url);
        if ($scheme === null || ! in_array($scheme, $schemes, true)) {
            return 'This link type is not allowed.';
        }
        if (in_array($scheme, self::WEB_SCHEMES, true) && ! $this->host($url)) {
            return 'Invalid URL format. Please check the URL format.';
        }
        $result = $this->assess($url, $text, remote: true);
        $blocked = array_intersect($result['signals'], config('abuse.block_categories', []));
        if ($blocked !== []) {
            return 'This destination is not allowed due to our security policy.';
        }

        return null;
    }

    /**
     * Score a URL + text. `signals` lists every matched category.
     *
     * @return array{score: int, signals: array<int, string>}
     */
    public function assess(?string $url, string $text = '', bool $remote = false): array
    {
        $signals = [];
        $haystack = trim($text.' '.$url);
        foreach (config('abuse.patterns', []) as $category => $pattern) {
            if ($haystack !== '' && preg_match($pattern, $haystack)) {
                $signals[] = $category;
            }
        }
        $url = trim((string) $url);
        if ($url !== '') {
            $scheme = $this->scheme($url);
            if ($scheme !== null && in_array($scheme, self::DANGEROUS_SCHEMES, true)) {
                $signals[] = 'dangerous_scheme';
            }
            $host = $this->host($url);
            if ($host) {
                if (filter_var($host, FILTER_VALIDATE_IP)) {
                    $signals[] = 'ip_host';
                }
                if (str_contains($host, 'xn--')) {
                    $signals[] = 'punycode';
                }
                if (in_array($host, config('abuse.shorteners', []), true)) {
                    $signals[] = 'chained_shortener';
                }
                if (in_array($host, config('abuse.file_hosts', []), true)) {
                    $signals[] = 'file_host';
                }
                $tld = substr((string) strrchr($host, '.'), 1);
                if (in_array($tld, config('abuse.risky_tlds', []), true)) {
                    $signals[] = 'risky_tld';
                }
                $category = $this->blacklistCategory($host);
                if ($category !== false) {
                    $signals[] = 'blacklisted';
                    if ($category && array_key_exists($category, config('abuse.weights', []))) {
                        $signals[] = $category;
                    }
                }
                if ($remote && in_array($scheme, self::WEB_SCHEMES, true) && $this->isKnownMalicious($url, $host)) {
                    $signals[] = 'malware';
                }
            }
            if ($category = $this->feedCategory($url)) {
                $signals[] = $category;
            }
            if (preg_match('/\.(apk|exe|scr|msi|bat|cmd|vbs|jar)(\?|#|$)/i', $url)) {
                $signals[] = 'executable_download';
            }
        }
        $signals = array_values(array_unique($signals));
        $weights = config('abuse.weights', []);
        $score = array_sum(array_map(fn ($s) => (int) ($weights[$s] ?? 0), $signals));

        return ['score' => $score, 'signals' => $signals];
    }

    public function host(?string $url): ?string
    {
        $host = parse_url(trim((string) $url), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower(trim($host, '[]')));
    }

    public function scheme(?string $url): ?string
    {
        // Strip whitespace/control chars browsers ignore (e.g. "java\tscript:")
        $clean = preg_replace('/[\x00-\x20]+/', '', (string) $url);
        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $clean, $m)) {
            return null;
        }

        return strtolower($m[1]);
    }

    /**
     * Blacklist category for a host: string|null when listed, false when not.
     */
    public function blacklistCategory(string $host): string|false|null
    {
        $this->blacklist ??= $this->loadBlacklist();
        if (array_key_exists($host, $this->blacklist['exact'])) {
            return $this->blacklist['exact'][$host];
        }
        foreach ($this->blacklist['wildcard'] as [$pattern, $category]) {
            if (preg_match($pattern, $host)) {
                return $category;
            }
        }

        return false;
    }

    public function forgetBlacklist(): void
    {
        $this->blacklist = null;
    }

    private function loadBlacklist(): array
    {
        $list = ['exact' => [], 'wildcard' => []];
        if (! Schema::hasTable('domain_blacklist')) {
            return $list;
        }
        foreach (DB::table('domain_blacklist')->get(['domain', 'match_type', 'category']) as $row) {
            $domain = preg_replace('/^www\./', '', strtolower(trim($row->domain)));
            if ($row->match_type === 'wildcard') {
                $pattern = '/^'.str_replace(['.', '*'], ['\.', '.*'], $domain).'$/i';
                $list['wildcard'][] = [$pattern, $row->category];
            } else {
                $list['exact'][$domain] = $row->category;
            }
        }

        return $list;
    }

    /**
     * Google Safe Browsing + URLhaus. Fails open: provider errors never block.
     */
    private function isKnownMalicious(string $url, string $host): bool
    {
        $cfg = config('abuse.providers');
        if (empty($cfg['safe_browsing_key']) && empty($cfg['urlhaus_auth_key']) && empty($cfg['spamhaus_dbl'])) {
            return false;
        }

        return Cache::remember('abuse:remote:'.sha1($url), now()->addHours((int) ($cfg['cache_hours'] ?? 12)), function () use ($url, $host, $cfg) {
            return $this->spamhausHit($host, $cfg) || $this->safeBrowsingHit($url, $cfg) || $this->urlhausHit($host, $cfg);
        });
    }

    /**
     * Spamhaus DBL over DNS (free for low volume, no key). Only 127.0.1.2-99
     * (spam/phish/malware/botnet domains) count; 127.0.1.1xx means a legit domain
     * with abused content, and 127.255.255.x means our resolver is refused
     * (public resolvers are blocked) - both are ignored.
     */
    private function spamhausHit(string $host, array $cfg): bool
    {
        if (empty($cfg['spamhaus_dbl']) || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        $records = @dns_get_record($host.'.dbl.spamhaus.org', DNS_A) ?: [];
        foreach ($records as $record) {
            if (preg_match('/^127\.0\.1\.(\d+)$/', $record['ip'] ?? '', $m) && (int) $m[1] >= 2 && (int) $m[1] < 100) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exact-URL match against the local URLhaus/OpenPhish feeds (abuse:feeds).
     */
    private function feedCategory(string $url): ?string
    {
        $this->feeds ??= collect(array_keys(UpdateThreatFeeds::FEEDS))
            ->mapWithKeys(function ($category) {
                $file = storage_path("app/threat-feeds/{$category}.txt");

                return [$category => is_file($file) ? array_flip(explode("\n", file_get_contents($file))) : []];
            })->all();
        $needle = UpdateThreatFeeds::normalize($url);
        foreach ($this->feeds as $category => $set) {
            if (isset($set[$needle])) {
                return $category;
            }
        }

        return null;
    }

    private function safeBrowsingHit(string $url, array $cfg): bool
    {
        if (empty($cfg['safe_browsing_key'])) {
            return false;
        }
        try {
            $response = Http::timeout($cfg['timeout'] ?? 5)->post(
                'https://safebrowsing.googleapis.com/v4/threatMatches:find?key='.$cfg['safe_browsing_key'],
                [
                    'client' => ['clientId' => 'hel.ink', 'clientVersion' => '2.1'],
                    'threatInfo' => [
                        'threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'],
                        'platformTypes' => ['ANY_PLATFORM'],
                        'threatEntryTypes' => ['URL'],
                        'threatEntries' => [['url' => $url]],
                    ],
                ]
            );

            return $response->ok() && ! empty($response->json('matches'));
        } catch (\Throwable $e) {
            Log::warning('Safe Browsing lookup failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function urlhausHit(string $host, array $cfg): bool
    {
        if (empty($cfg['urlhaus_auth_key'])) {
            return false;
        }
        try {
            $response = Http::timeout($cfg['timeout'] ?? 5)
                ->withHeaders(['Auth-Key' => $cfg['urlhaus_auth_key']])
                ->asForm()
                ->post('https://urlhaus-api.abuse.ch/v1/host/', ['host' => $host]);
            if (! $response->ok() || $response->json('query_status') !== 'ok') {
                return false;
            }
            // Only count hosts with currently online malicious URLs.
            foreach ((array) $response->json('urls') as $entry) {
                if (($entry['url_status'] ?? null) === 'online') {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('URLhaus lookup failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
