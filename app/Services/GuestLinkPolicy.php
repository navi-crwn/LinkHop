<?php

namespace App\Services;

use App\Models\AbuseFlag;
use App\Models\GuestLinkLog;
use App\Models\Link;
use App\Support\GuestDestination;
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

    public const REFUSED_HOURLY_CAP = 'hourly_cap';

    public const REFUSED_ROLLING_DAY_CAP = 'rolling_day_cap';

    public function __construct(private readonly DomainAge $ages) {}

    /**
     * @return array{allowed: bool, reason: ?string, message: ?string, safety_status: ?string, creator_hash: ?string}
     */
    public function decide(string $url, ?string $ip): array
    {
        $cfg = (array) config('shortener.guest_policy', []);
        $hash = VisitorHash::make($ip);
        $allow = fn (?string $status, ?bool $burst = null) => [
            'allowed' => true, 'reason' => null, 'message' => null, 'safety_status' => $status,
            'creator_hash' => $hash, 'burst' => $burst ?? false,
        ];
        if (empty($cfg['enabled'])) {
            return $allow(null);
        }
        // Caps that do not restart at midnight: a kit is happy to wait for the clock. The hourly one stops a burst
        // from one connection; the rolling-day one counts the last 24 hours whenever it is asked.
        $perHour = (int) ($cfg['per_ip_per_hour'] ?? 0);
        if ($perHour > 0 && $hash !== null && $this->madeSince($hash, now()->subHour()) >= $perHour) {
            return $this->refuse(self::REFUSED_HOURLY_CAP, __('Guests can make :max short links an hour from one connection. Create a free account to make more.', ['max' => $perHour]), $hash);
        }
        $perRolling = (int) ($cfg['per_ip_per_rolling_day'] ?? 0);
        if ($perRolling > 0 && $hash !== null && $this->madeSince($hash, now()->subDay()) >= $perRolling) {
            return $this->refuse(self::REFUSED_ROLLING_DAY_CAP, __('Guests can make :max short links a day from one connection. Create a free account to make more.', ['max' => $perRolling]), $hash);
        }
        $cap = (int) ($cfg['per_ip_per_day'] ?? 0);
        if ($cap > 0 && $hash !== null && $this->madeToday($hash) >= $cap) {
            return $this->refuse(self::REFUSED_DAILY_CAP, __('Guests can make :max short links a day from one connection. Create a free account to make more.', ['max' => $cap]), $hash);
        }
        $host = $this->host($url);
        if ($host === null) {
            return $allow(null);
        }
        // Many connections to one destination in a short window: a kit handing out the same address. Checked before
        // the allowlist, because a burst is the point where being well known stops being a reason to trust it. The
        // link is held behind the warning page and flagged; nothing is suspended and the links already made stay.
        $burst = $this->isBurst($url, $hash);
        if ($this->isAllowlisted($host, (array) ($cfg['allowlist'] ?? []))) {
            return $burst ? $allow(Link::SAFETY_PENDING, true) : $allow(null);
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

        return $allow($burst || ! empty($cfg['pending_unknown_hosts']) ? Link::SAFETY_PENDING : null, $burst);
    }

    /** Guest links this connection made since a moment, from the log (across midnight). */
    public function madeSince(string $creatorHash, \DateTimeInterface $since): int
    {
        return GuestLinkLog::query()->where('creator_hash', $creatorHash)->where('created_at', '>=', $since)->count();
    }

    /**
     * True when guest_policy.burst_ips different connections reached this destination inside
     * guest_policy.burst_window_minutes. Distinct connections only: one person shortening the same site several
     * times is not a burst.
     */
    public function isBurst(string $url, ?string $hash): bool
    {
        $cfg = (array) config('shortener.guest_policy', []);
        $ips = (int) ($cfg['burst_ips'] ?? 0);
        $destination = GuestDestination::normalize($url);
        if ($ips <= 0 || $destination === null) {
            return false;
        }
        $minutes = max(1, (int) ($cfg['burst_window_minutes'] ?? 60));

        return GuestLinkLog::query()
            ->where('destination', $destination)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->when($hash !== null, fn ($q) => $q->where('creator_hash', '!=', $hash))
            ->distinct()
            ->count('creator_hash') >= $ips;
    }

    /**
     * Put a held link in the abuse queue for review, with the reason. Called after the link exists, because a flag
     * points at the object it is about; the operator decides what happens next, not this class.
     */
    public function flagBurst(Link $link, string $url): void
    {
        $destination = GuestDestination::normalize($url) ?? $url;
        $signals = ['guest_burst'];
        $shape = PhishingShape::assess($url);
        if ($shape['hit']) {
            $signals = array_values(array_unique(array_merge($signals, ['phishing_shape'], $shape['signals'])));
        }
        AbuseFlag::firstOrCreate(
            ['subject_type' => 'link', 'subject_id' => $link->id],
            [
                'user_id' => null,
                'score' => 60,
                'signals' => $signals,
                'action' => AbuseFlag::ACTION_FLAGGED,
                'status' => AbuseFlag::STATUS_OPEN,
                'summary' => 'guest burst: '.((int) config('shortener.guest_policy.burst_ips', 0)).'+ connections reached '.$destination.' within '.((int) config('shortener.guest_policy.burst_window_minutes', 60)).' minutes; this link is held',
            ],
        );
    }

    /** Remember what this guest made, so the next request can count it (also across midnight). */
    public function remember(string $url, ?string $hash, ?string $slug): void
    {
        if ($hash === null) {
            return;
        }
        GuestLinkLog::create([
            'creator_hash' => $hash,
            'destination' => GuestDestination::normalize($url) ?? '',
            'slug' => $slug,
            'created_at' => now(),
        ]);
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
