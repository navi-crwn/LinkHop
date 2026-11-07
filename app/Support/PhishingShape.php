<?php

namespace App\Support;

/**
 * The shape of a phishing kit's landing address, read off the address alone, without fetching it. The Telegram
 * account-takeover kit that reached hel.ink on 2026-11-05 hands every victim an address like
 * https://mjdgdqgk.fnjphdp.com/wqamhcsom?r=AbC&t=123: a machine-made subdomain, a machine-made path segment,
 * two one-letter tracking parameters, on a domain registered days before. One of those alone means little
 * (CDN hosts have random labels too); together they are the kit.
 *
 * Used by links:sweep-phishing, the guest policy (refuse for guests) and LinkHopSafety (a score for everyone).
 */
final class PhishingShape
{
    /** Second-level labels that are public suffixes under a country code (co.uk, com.au, co.id, or.id). */
    private const PUBLIC_SECOND_LEVELS = ['co', 'com', 'net', 'org', 'gov', 'edu', 'ac', 'or', 'go', 'web', 'my', 'biz', 'sch', 'mil', 'info', 'ne', 'id', 'in'];

    /** Phrases of the kit's page: title, form labels and the confirm button. Matched against fetched HTML (--fetch). */
    public const PAGE_PATTERNS = [
        '/account[\s_-]*security[\s_-]*compliance/i',
        '/two[\s_-]*step[\s_-]*password/i',
        '/yes,?[\s_-]*it[\s_-]*was[\s_-]*me\b/i',
        '/restore[\s_-]*(your[\s_-]*)?account[\s_-]*status/i',
        '/verification[\s_-]*code.{0,80}(telegram|two[\s_-]*step)/is',
    ];

    /**
     * Every signal the address shows, and whether together they make the shape of a kit address.
     *
     * @return array{hit: bool, signals: list<string>, host: ?string, domain: ?string}
     */
    public static function assess(?string $url): array
    {
        $url = trim((string) $url);
        $parts = $url === '' ? false : parse_url($url);
        $host = is_array($parts) && ! empty($parts['host']) ? strtolower(trim($parts['host'], '[]')) : null;
        $host = $host === null ? null : (preg_replace('/^www\./', '', $host) ?: null);
        if ($host === null || filter_var($host, FILTER_VALIDATE_IP)) {
            return ['hit' => false, 'signals' => [], 'host' => $host, 'domain' => $host];
        }
        $domain = self::registrable($host);
        $signals = [];
        if (self::isKitHost($host)) {
            $signals[] = 'kit_host';
        }
        $path = (string) ($parts['path'] ?? '');
        if (self::hasRandomSubdomain($host)) {
            $signals[] = 'random_subdomain';
        }
        if (self::hasRandomPath($path)) {
            $signals[] = 'random_path';
        }
        if (self::hasTrackingParams((string) ($parts['query'] ?? ''))) {
            $signals[] = 'tracking_params';
        }
        $tld = substr((string) strrchr($domain, '.'), 1);
        if ($tld !== '' && in_array($tld, (array) config('safety.risky_tlds', []), true)) {
            $signals[] = 'risky_tld';
        }
        if (self::hasKitPath($path) && in_array('tracking_params', $signals, true)) {
            $signals[] = 'kit_path';
        }
        // A known kit host or the observed kit path is enough with its tracking pair. Otherwise two machine-made
        // parts, or one plus the tracking pair on a throwaway TLD, is the shape. A subdomain plus the random path
        // and tracking pair also catches the same kit after it changes to a readable-looking subdomain.
        $has = fn (string $s) => in_array($s, $signals, true);
        $hit = $has('kit_host')
            || $has('kit_path')
            || ($has('random_subdomain') && ($has('random_path') || $has('tracking_params')))
            || ($has('random_path') && $has('tracking_params') && ($has('risky_tld') || $host !== $domain));

        return ['hit' => $hit, 'signals' => $signals, 'host' => $host, 'domain' => $domain];
    }

    /** The landing path of the observed Telegram account-takeover kit. */
    public static function hasKitPath(string $path): bool
    {
        $segment = explode('/', trim($path, '/'))[0] ?? '';

        return in_array(strtolower($segment), ['wqamhcsom'], true);
    }

    /** True when the fetched page reads like the kit's form. */
    public static function pageMatches(string $html): bool
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(script|style)\b.*?<\/\1>/is', ' ', $html) ?? ''), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        foreach (self::PAGE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /** The host, or a parent of it, is a kit host from config or the extra list. */
    public static function isKitHost(string $host, array $extra = []): bool
    {
        foreach (array_merge((array) config('shortener.guest_policy.kit_hosts', []), $extra) as $kit) {
            $kit = strtolower(trim((string) $kit));
            if ($kit !== '' && ($host === $kit || str_ends_with($host, '.'.$kit))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The registrable domain of a host: two labels (example.com), three under a public second-level suffix of a
     * country code (example.co.uk, example.co.id). An IP literal or a bare name is returned as it is.
     */
    public static function registrable(string $host): string
    {
        $host = strtolower(trim($host, '.'));
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $labels = explode('.', $host);
        $n = count($labels);
        if ($n <= 2) {
            return $host;
        }
        $keep = strlen($labels[$n - 1]) === 2 && in_array($labels[$n - 2], self::PUBLIC_SECOND_LEVELS, true) ? 3 : 2;

        return implode('.', array_slice($labels, -$keep));
    }

    /** A label left of the registrable domain that reads as machine-made. CDNs and the family never count. */
    public static function hasRandomSubdomain(string $host): bool
    {
        $host = strtolower($host);
        foreach ((array) config('safety.known_domains', []) as $known) {
            if ($host === $known || str_ends_with($host, '.'.$known)) {
                return false;
            }
        }
        $domain = self::registrable($host);
        if ($domain === $host) {
            return false;
        }
        foreach (explode('.', substr($host, 0, -strlen($domain) - 1)) as $label) {
            if (self::looksRandom($label)) {
                return true;
            }
        }

        return false;
    }

    /** The first path segment reads as machine-made (/wqamhcsom); a slug with hyphens, dots or underscores was written by a person. */
    public static function hasRandomPath(string $path): bool
    {
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        if ($segments === [] || ! preg_match('/^[A-Za-z0-9]+$/', $segments[0])) {
            return false;
        }

        // A path label is a little looser than a host label: the kit's own /wqamhcsom has two vowels in nine letters.
        return self::looksRandom($segments[0], 0.25);
    }

    /** A query of nothing but one-letter keys that includes the kit's r= and t= pair. */
    public static function hasTrackingParams(string $query): bool
    {
        if ($query === '') {
            return false;
        }
        parse_str($query, $params);
        $keys = array_map('strval', array_keys($params));
        if ($keys === [] || array_filter($keys, fn (string $k) => strlen($k) > 1) !== []) {
            return false;
        }

        return in_array('r', $keys, true) && in_array('t', $keys, true);
    }

    /**
     * Seven or more letters and digits whose letters are almost all consonants (at most one vowel in five, unless the
     * caller allows a little more) read as random: mjdgdqgk, qwrtzpsk, f3b9c2d7e1. Words, short labels and plain numbers do not.
     */
    public static function looksRandom(string $label, float $maxVowelShare = 0.2): bool
    {
        $label = strtolower($label);
        $alnum = preg_replace('/[^a-z0-9]/', '', $label) ?? '';
        $letters = preg_replace('/[^a-z]/', '', $alnum) ?? '';
        if (strlen($alnum) < 7 || strlen($letters) < 5) {
            return false;
        }
        $vowels = preg_match_all('/[aeiouy]/', $letters);

        return $vowels / strlen($letters) <= $maxVowelShare;
    }
}
