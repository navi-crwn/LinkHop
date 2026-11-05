<?php

namespace App\Support;

use App\Models\AbuseFlag;
use App\Models\Link;
use Carbon\CarbonImmutable;
use Hop\Auth\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The daily picture of guest links: how many were made in the window, on which domains, how many are still behind
 * the warning page, how many the scan or the sweep disabled. A domain with guest_policy.digest_spike or more new
 * links is a spike. Built by links:guest-digest, served to Imperium at GET /api/hop/x/guest-digest, and pushed to
 * Imperium's notification inbox (POST /hop/notify, type guest.digest) when HOP_NOTIFY_URL is set.
 */
final class GuestDigest
{
    public const CACHE_KEY = 'guest:digest:last';

    public const TYPE = 'guest.digest';

    /** @return array<string, mixed> */
    public function build(int $hours = 24, ?CarbonImmutable $until = null): array
    {
        $until ??= CarbonImmutable::now();
        $since = $until->subHours(max(1, $hours));
        $spike = (int) config('shortener.guest_policy.digest_spike', 10);
        $links = Link::query()->whereNull('user_id')->whereBetween('created_at', [$since, $until])
            ->get(['id', 'target_url', 'status', 'safety_status', 'creator_hash']);
        $domains = [];
        $creators = [];
        foreach ($links as $link) {
            $host = (string) parse_url($link->target_url, PHP_URL_HOST);
            $domain = $host === '' ? '?' : PhishingShape::registrable(preg_replace('/^www\./', '', strtolower(trim($host, '[]'))));
            $d = &$domains[$domain];
            $d['domain'] = $domain;
            $d['links'] = ($d['links'] ?? 0) + 1;
            $d['pending'] = ($d['pending'] ?? 0) + ($link->safety_status === Link::SAFETY_PENDING ? 1 : 0);
            $d['disabled'] = ($d['disabled'] ?? 0) + ($link->status !== Link::STATUS_ACTIVE ? 1 : 0);
            unset($d);
            if ($link->creator_hash) {
                $creators[$link->creator_hash] = true;
            }
        }
        usort($domains, fn ($a, $b) => $b['links'] <=> $a['links']);
        $domains = array_map(fn ($d) => $d + ['spike' => $d['links'] >= $spike], array_slice($domains, 0, 50));

        return [
            'generated_at' => $until->toIso8601String(),
            'window' => ['from' => $since->toIso8601String(), 'to' => $until->toIso8601String(), 'hours' => $hours],
            'guest_links' => $links->count(),
            'pending' => $links->where('safety_status', Link::SAFETY_PENDING)->count(),
            'cleared' => $links->where('safety_status', Link::SAFETY_SAFE)->count(),
            'disabled' => $links->where('status', '!=', Link::STATUS_ACTIVE)->count(),
            'flags' => AbuseFlag::where('subject_type', 'link')->whereNull('user_id')->whereBetween('created_at', [$since, $until])->count(),
            'creators' => count($creators),
            'spike_threshold' => $spike,
            'spikes' => array_values(array_map(fn ($d) => $d['domain'], array_filter($domains, fn ($d) => $d['spike']))),
            'domains' => $domains,
        ];
    }

    /** @param  array<string, mixed>  $digest */
    public function store(array $digest): string
    {
        Cache::forever(self::CACHE_KEY, $digest);
        $path = storage_path('app/sweeps/guest-digest-'.CarbonImmutable::parse($digest['generated_at'])->format('Y-m-d').'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($digest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /** @return array<string, mixed>|null */
    public function last(): ?array
    {
        $digest = Cache::get(self::CACHE_KEY);

        return is_array($digest) ? $digest : null;
    }

    /**
     * Posts the digest to Imperium's inbox as a signed HOP request from this app (hop/auth's secret). Off without
     * HOP_NOTIFY_URL. Imperium has to accept the type guest.digest for it to land; a refusal is logged, never retried.
     *
     * @param  array<string, mixed>  $digest
     * @return int|null the HTTP status, or null when nothing was sent
     */
    public function push(array $digest): ?int
    {
        $base = rtrim((string) config('hop.notify_url', ''), '/');
        $secrets = Ticket::resolveSecrets();
        if ($base === '' || $secrets === []) {
            return null;
        }
        $app = (string) config('hop-auth.app', 'linkhop');
        $body = json_encode([
            'id' => (string) Str::ulid(),
            'type' => self::TYPE,
            'at' => CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z'),
            'app' => $app,
            'env' => (string) config('app.env', 'production'),
            'data' => [
                'guest_links' => $digest['guest_links'], 'pending' => $digest['pending'], 'disabled' => $digest['disabled'],
                'flags' => $digest['flags'], 'creators' => $digest['creators'], 'spikes' => $digest['spikes'],
                'top_domains' => array_map(fn ($d) => [$d['domain'], $d['links']], array_slice($digest['domains'], 0, 10)),
            ],
        ], JSON_UNESCAPED_SLASHES);
        $ts = (string) time();
        $nonce = HopSignature::b64(random_bytes(16));
        $signature = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign($app, $ts, $nonce, 'POST', '/hop/notify', (string) $body), $secrets[0], true));
        try {
            $response = Http::withHeaders([
                'X-HOP-App' => $app, 'X-HOP-Timestamp' => $ts, 'X-HOP-Nonce' => $nonce, 'X-HOP-Actor' => 'system', 'X-HOP-Signature' => $signature,
            ])->withBody((string) $body, 'application/json')->timeout(2)->connectTimeout(1)->post($base.'/hop/notify');
        } catch (\Throwable $e) {
            Log::info('guest digest not delivered to Imperium', ['error' => $e->getMessage()]);

            return null;
        }
        if (! $response->successful()) {
            Log::info('guest digest refused by Imperium', ['status' => $response->status(), 'error' => $response->json('error')]);
        }

        return $response->status();
    }
}
