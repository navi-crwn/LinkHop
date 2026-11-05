<?php

namespace App\Services;

use App\Models\Link;
use App\Support\PhishingShape;
use App\Support\VisitorHash;

/**
 * What a guest (no account) may shorten, on top of hop/safety's rules for everyone (config/shortener.php,
 * guest_policy). Answers in three ways: refuse with a translated message, allow with safety_status = pending
 * (a warning page until abuse:scan clears it), or allow as it is (an allowlisted destination).
 */
class GuestLinkPolicy
{
    public const REFUSED_DAILY_CAP = 'daily_cap';

    public const REFUSED_YOUNG_DOMAIN = 'young_domain';

    public const REFUSED_UNKNOWN_AGE = 'unknown_age';

    public const REFUSED_RANDOM_SUBDOMAIN = 'random_subdomain';

    public const REFUSED_PHISHING_SHAPE = 'phishing_shape';

    public function __construct(private readonly DomainAge $ages) {}

    /**
     * @return array{allowed: bool, reason: ?string, message: ?string, safety_status: ?string, creator_hash: ?string}
     */
    public function decide(string $url, ?string $ip): array
    {
        $cfg = (array) config('shortener.guest_policy', []);
        $hash = VisitorHash::make($ip);
        $allow = fn (?string $status) => ['allowed' => true, 'reason' => null, 'message' => null, 'safety_status' => $status, 'creator_hash' => $hash];
        if (empty($cfg['enabled'])) {
            return $allow(null);
        }
        $cap = (int) ($cfg['per_ip_per_day'] ?? 0);
        if ($cap > 0 && $hash !== null && $this->madeToday($hash) >= $cap) {
            return $this->refuse(self::REFUSED_DAILY_CAP, __('Guests can make :max short links a day from one connection. Create a free account to make more.', ['max' => $cap]), $hash);
        }
        $host = $this->host($url);
        if ($host === null) {
            return $allow(null);
        }
        if ($this->isAllowlisted($host, (array) ($cfg['allowlist'] ?? []))) {
            return $allow(null);
        }
        $shape = PhishingShape::assess($url);
        if (! empty($cfg['refuse_phishing_shape']) && $shape['hit']) {
            return $this->refuse(self::REFUSED_PHISHING_SHAPE, $this->accountMessage(), $hash);
        }
        if (! empty($cfg['refuse_random_subdomain']) && in_array('random_subdomain', $shape['signals'], true)) {
            return $this->refuse(self::REFUSED_RANDOM_SUBDOMAIN, $this->accountMessage(), $hash);
        }
        $minAge = (int) ($cfg['min_domain_age_days'] ?? 0);
        if ($minAge > 0 && ! empty($cfg['domain_age_lookup'])) {
            $age = filter_var($host, FILTER_VALIDATE_IP) ? null : $this->ages->ageInDays($host);
            if ($age === null && ! empty($cfg['unknown_age_is_young'])) {
                return $this->refuse(self::REFUSED_UNKNOWN_AGE, $this->accountMessage(), $hash);
            }
            if ($age !== null && $age < $minAge) {
                return $this->refuse(self::REFUSED_YOUNG_DOMAIN, $this->accountMessage(), $hash);
            }
        }

        return $allow(! empty($cfg['pending_unknown_hosts']) ? Link::SAFETY_PENDING : null);
    }

    /** Guest links this connection made since midnight (the server's day). */
    public function madeToday(string $creatorHash): int
    {
        return Link::query()->whereNull('user_id')->where('creator_hash', $creatorHash)
            ->where('created_at', '>=', now()->startOfDay())->count();
    }

    public function isAllowlisted(string $host, array $allowlist): bool
    {
        foreach ($allowlist as $domain) {
            $domain = strtolower(trim((string) $domain));
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                return true;
            }
        }

        return false;
    }

    private function host(string $url): ?string
    {
        $host = parse_url(trim($url), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower(trim($host, '[]')));
    }

    private function accountMessage(): string
    {
        return __('This destination is on a very new or unknown site, so it cannot be shortened as a guest. Sign in with a free HOP ID to shorten it.');
    }

    private function refuse(string $reason, string $message, ?string $hash): array
    {
        return ['allowed' => false, 'reason' => $reason, 'message' => $message, 'safety_status' => null, 'creator_hash' => $hash];
    }
}
