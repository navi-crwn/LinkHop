<?php

namespace App\Support;

use Hop\Safety\UrlGuard;

/**
 * Decides whether a webhook address may be called: https only, no login in the address, and every address the host
 * resolves to has to be a public one (no loopback, private, link-local, shared-address or cloud-metadata ranges,
 * also when written inside an IPv6 address). The delivery pins the connection to the address checked here, so
 * a host that changes its answer between the check and the call cannot reach an internal network.
 */
class WebhookGuard extends UrlGuard
{
    /**
     * @return array{0: string|null, 1: list<string>, 2: string, 3: int} problem (a code for the message), resolved IPs, host, port
     */
    public function inspect(?string $url): array
    {
        $parts = is_string($url) ? parse_url($url) : false;
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return ['invalid', [], '', 0];
        }
        $host = strtolower(trim($parts['host'], '[]'));
        $port = (int) ($parts['port'] ?? 443);
        if (strtolower($parts['scheme']) !== 'https') {
            return ['https', [], $host, $port];
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return ['userinfo', [], $host, $port];
        }
        if ($port < 1 || $port > 65535) {
            return ['invalid', [], $host, $port];
        }
        if ($host === 'localhost' || preg_match('/\.(localhost|local|internal|lan|home|corp|intranet)$/', $host)) {
            return ['private', [], $host, $port];
        }
        $ips = $this->resolveHost($host);
        if ($ips === []) {
            return ['resolve', [], $host, $port];
        }
        foreach ($ips as $ip) {
            if (! $this->isPublicAddress($ip)) {
                return ['private', $ips, $host, $port];
            }
        }

        return [null, array_values($ips), $host, $port];
    }

    public function isSafe(?string $url): bool
    {
        return $this->inspect($url)[0] === null;
    }

    /** The message for a problem code from inspect(). */
    public static function message(string $code): string
    {
        return match ($code) {
            'https' => __('The address has to start with https://'),
            'userinfo' => __('The address cannot contain a user name or password.'),
            'private' => __('This address points to a private or internal network, which is not allowed.'),
            'resolve' => __('We could not find this address. Check the spelling.'),
            default => __('That is not a valid web address.'),
        };
    }

    public function isPublicAddress(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        $bytes = inet_pton($ip);
        if ($bytes !== false && strlen($bytes) === 16) {
            // An IPv4 address carried inside an IPv6 one: IPv4-mapped, NAT64 and 6to4 take the checks of the IPv4 address.
            $embedded = null;
            if (str_starts_with($bytes, str_repeat("\0", 10)."\xff\xff")) {
                $embedded = substr($bytes, 12, 4);
            } elseif (str_starts_with($bytes, "\x00\x64\xff\x9b".str_repeat("\0", 8))) {
                $embedded = substr($bytes, 12, 4);
            } elseif (str_starts_with($bytes, "\x20\x02")) {
                $embedded = substr($bytes, 2, 4);
            }
            if ($embedded !== null && ! $this->isPublicAddress(inet_ntop($embedded))) {
                return false;
            }
            // fe80::/10 (link-local), fec0::/10 (site-local), :: and ::1
            $first = ord($bytes[0]);
            $second = ord($bytes[1]);
            if ($first === 0xFE && ($second & 0xC0) >= 0x80) {
                return false;
            }
            if ($bytes === str_repeat("\0", 16) || $bytes === str_repeat("\0", 15)."\x01") {
                return false;
            }
        } elseif ($bytes !== false && strlen($bytes) === 4) {
            // 100.64.0.0/10 (shared address space) is not flagged by PHP's filter
            if (ord($bytes[0]) === 100 && (ord($bytes[1]) & 0xC0) === 64) {
                return false;
            }
        }

        return $this->isPublicIp($ip);
    }
}
