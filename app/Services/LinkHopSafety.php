<?php

namespace App\Services;

use App\Support\EncryptedNoteLink;
use App\Support\PhishingShape;
use App\Support\WebhookGuard;
use Hop\Safety\EncryptedNoteLink as NoteAddress;
use Hop\Safety\LinkSafety;

/**
 * hop/safety's LinkSafety plus two rules that belong to this app, bound over the package's in AppServiceProvider.
 *
 * Every place that accepts a destination asks rejectionReason() first (web forms, the API, CSV import, the
 * routing and app-link fields, the bio blocks), so the encrypted-note rule lives here once.
 */
class LinkHopSafety extends LinkSafety
{
    /** Whether assess() may look up a domain's age (one RDAP request per domain, cached). Off on the request path. */
    private bool $domainAge = false;

    public function __construct(private readonly EncryptedNoteLink $notes, private readonly DomainAge $ages) {}

    /** abuse:scan and the sweep turn this on; forms and the API never wait for a registry. */
    public function withDomainAge(bool $on = true): static
    {
        $this->domainAge = $on;

        return $this;
    }

    /** Refuses the address of an encrypted NoteHop note: its key is in the fragment, and we would store it. */
    public function rejectionReason(?string $url, string $text = '', array $schemes = self::WEB_SCHEMES): ?string
    {
        if ($this->notes->matches($url) || $this->notes->holdsKey($url) || $this->notes->holdsKey($text)) {
            return $this->notes->message();
        }
        if ($problem = $this->addressProblem((string) $url)) {
            return WebhookGuard::message($problem);
        }

        return parent::rejectionReason($url, $text, $schemes);
    }

    /**
     * The same two refusals as for webhooks, read off the address the way a browser reads it, without looking up a name.
     * A login shows one host and opens another ("https://paypal.com@evil.example/"); a private address leads into the
     * visitor's own network. Null for any other address, and for one that is not http or https.
     */
    private function addressProblem(string $url): ?string
    {
        $clean = preg_replace('/[\t\r\n]/', '', trim($url));
        if (! preg_match('~^https?:[/\\\\]*([^/\\\\?#]*)~i', (string) $clean, $m)) {
            return null;
        }
        $at = strrpos($m[1], '@');
        if ($at !== false) {
            return 'userinfo';
        }
        if (config('shortener.allow_private_destinations')) {
            return null;
        }
        $host = (string) preg_replace('/:\d*$/', '', $m[1]);
        $bracketed = str_starts_with($host, '[');
        $host = (string) NoteAddress::normalizeHost($host);
        if ($host === 'localhost' || preg_match('/\.(localhost|local|internal|lan|home|corp|intranet)$/', $host)) {
            return 'private';
        }
        $ip = $bracketed ? $host : self::ipv4($host);

        return $ip !== null && ! app(WebhookGuard::class)->isPublicAddress($ip) ? 'private' : null;
    }

    /** The IPv4 address a host name stands for in a browser: 127.1, 2130706433, 0x7f000001 and 0177.0.0.1 are all one. */
    private static function ipv4(string $host): ?string
    {
        $parts = explode('.', $host);
        if (count($parts) > 4 || array_filter($parts, fn (string $p) => ! preg_match('/^(0x[0-9a-f]*|[0-9]+)$/', $p)) !== []) {
            return null;
        }
        $numbers = array_map(fn (string $p) => str_starts_with($p, '0x') ? (int) hexdec($p) : (int) (strlen($p) > 1 && $p[0] === '0' ? octdec($p) : $p), $parts);
        $last = array_pop($numbers);
        $value = $last;
        foreach ($numbers as $i => $number) {
            $value += $number * 256 ** (3 - $i);
        }

        return $value >= 0 && $value <= 0xFFFFFFFF ? long2ip((int) $value) : null;
    }

    /**
     * A fragment never reaches the destination server, so it is not sent to the outside lookups (Safe Browsing,
     * URLhaus) either. The keyword patterns still read the whole address.
     */
    public function assess(?string $url, string $text = '', bool $remote = false): array
    {
        $bare = EncryptedNoteLink::withoutFragment($url);
        if (! $remote || $bare === $url) {
            $result = parent::assess($url, $text, $remote);
        } else {
            $local = parent::assess($url, $text, false);
            $far = parent::assess($bare, $text, true);
            $result = ['score' => 0, 'signals' => array_values(array_unique([...$local['signals'], ...$far['signals']]))];
        }

        return $this->withOwnSignals($url, $result);
    }

    /**
     * LinkHop's own signals on top of the package's: the shape of a phishing kit's address (App\Support\PhishingShape)
     * and, when allowed, a domain registered fewer than guest_policy.min_domain_age_days ago. Weights come from
     * config/shortener.php (guest_policy.weights); the package's weights stay as they are.
     *
     * @param  array{score: int, signals: array<int, string>}  $result
     * @return array{score: int, signals: array<int, string>}
     */
    private function withOwnSignals(?string $url, array $result): array
    {
        $signals = $result['signals'];
        $shape = PhishingShape::assess($url);
        if ($shape['hit']) {
            $signals[] = 'phishing_shape';
        }
        $minAge = (int) config('shortener.guest_policy.min_domain_age_days', 0);
        if ($this->domainAge && $minAge > 0 && $shape['host'] !== null && ! filter_var($shape['host'], FILTER_VALIDATE_IP)
            && ! app(GuestLinkPolicy::class)->isAllowlisted($shape['host'], (array) config('shortener.guest_policy.allowlist', []))) {
            $age = $this->ages->ageInDays($shape['host']);
            if ($age !== null && $age < $minAge) {
                $signals[] = 'young_domain';
            }
        }
        $signals = array_values(array_unique($signals));
        $weights = array_merge((array) config('safety.weights', []), (array) config('shortener.guest_policy.weights', []));

        return ['score' => array_sum(array_map(fn ($s) => (int) ($weights[$s] ?? 0), $signals)), 'signals' => $signals];
    }
}
