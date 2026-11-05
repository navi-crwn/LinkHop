<?php

namespace App\Services;

use App\Support\PhishingShape;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * How many days ago a domain was registered, from its registry's RDAP record. The request goes to the RDAP
 * redirector (shortener.guest_policy.rdap_url, rdap.org), which forwards to the registry; the destination itself is
 * never asked. One lookup per registrable domain, remembered for a week; a failed lookup is not retried for an hour.
 */
class DomainAge
{
    public const UNKNOWN = null;

    /**
     * Days since registration, or null when the registry did not say or could not be reached.
     */
    public function ageInDays(string $host): ?int
    {
        $registered = $this->registeredAt($host);

        return $registered === null ? self::UNKNOWN : max(0, (int) $registered->diffInDays(now(), false));
    }

    /** The registration date, or null when unknown. */
    public function registeredAt(string $host): ?CarbonImmutable
    {
        $domain = PhishingShape::registrable(strtolower(trim($host)));
        if ($domain === '' || filter_var($domain, FILTER_VALIDATE_IP) || ! str_contains($domain, '.')) {
            return null;
        }
        $cfg = (array) config('shortener.guest_policy', []);
        $key = 'domain-age:'.$domain;
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return isset($cached['registered']) ? CarbonImmutable::parse($cached['registered']) : null;
        }
        $registered = $this->lookup($domain, $cfg);
        $ttl = $registered === null ? now()->addMinutes((int) ($cfg['rdap_failure_minutes'] ?? 60)) : now()->addDays((int) ($cfg['rdap_cache_days'] ?? 7));
        Cache::put($key, ['registered' => $registered?->toIso8601String()], $ttl);

        return $registered;
    }

    private function lookup(string $domain, array $cfg): ?CarbonImmutable
    {
        $base = rtrim((string) ($cfg['rdap_url'] ?? ''), '/');
        if ($base === '') {
            return null;
        }
        try {
            $response = Http::acceptJson()
                ->withHeaders(['User-Agent' => 'HEL.ink-LinkHop/1.0 (+'.public_url('').')'])
                ->timeout((int) ($cfg['rdap_timeout'] ?? 4))->connectTimeout(3)
                ->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['https']]])
                ->get($base.'/'.rawurlencode($domain));
        } catch (\Throwable $e) {
            Log::info('RDAP lookup failed', ['domain' => $domain, 'error' => $e->getMessage()]);

            return null;
        }
        if (! $response->ok()) {
            return null;
        }
        foreach ((array) $response->json('events', []) as $event) {
            if (is_array($event) && ($event['eventAction'] ?? null) === 'registration' && ! empty($event['eventDate'])) {
                try {
                    return CarbonImmutable::parse((string) $event['eventDate']);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }
}
