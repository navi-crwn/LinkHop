<?php

namespace App\Support;

use Hop\Safety\UrlGuard;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Asks a link's destination whether it still answers, the way a polite stranger would: a HEAD (a GET when the
 * server dislikes HEAD) with a short timeout, redirects followed by hand up to a cap, and every address, the
 * first and each redirect target, checked with UrlGuard so a destination can never lead us to an internal host.
 * The fragment (#...) is cut off before anything is asked: it is never part of a request, and for an encrypted
 * note it is the key.
 */
class DestinationChecker
{
    public const OK = 'ok';

    public const BROKEN = 'broken';

    /** Could not tell: not a web address, an internal host, or too many redirects. Never counts against a link. */
    public const SKIPPED = 'skipped';

    private const REDIRECTS = [301, 302, 303, 307, 308];

    /** Answers that mean "alive, but not for a robot": a page that exists and refuses us is not broken. */
    private const REFUSING = [401, 403, 429, 999];

    public function __construct(private readonly UrlGuard $guard) {}

    /** @return array{state: string, status: ?int} */
    public function check(string $url): array
    {
        $max = (int) config('shortener.monitor.max_redirects', 5);
        for ($hop = 0; $hop <= $max; $hop++) {
            $url = (string) EncryptedNoteLink::withoutFragment($url);
            $parts = parse_url($url);
            $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
            if ($host === '' || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
                return $this->result(self::SKIPPED);
            }
            // resolve once and connect to exactly the addresses that were checked, so a second DNS answer cannot swap in a private one
            $target = $this->guard->resolve($url);
            if ($target === null) {
                // An address that is private, loopback or link-local is not ours to knock on. A name that resolves
                // nowhere is a different matter: the site is gone.
                return $this->hostResolves($host) ? $this->result(self::SKIPPED) : $this->result(self::BROKEN);
            }
            try {
                $response = $this->ask($url, $this->guard->curlResolve($target));
            } catch (\Throwable) {
                return $this->result(self::BROKEN);
            }
            $status = $response->status();
            if (in_array($status, self::REDIRECTS, true) && ($location = $response->header('Location')) !== '') {
                $url = $this->absolute($location, $url);

                continue;
            }

            return $this->classify($status);
        }

        return $this->result(self::SKIPPED);
    }

    private function classify(int $status): array
    {
        return match (true) {
            $status >= 200 && $status < 400, in_array($status, self::REFUSING, true) => $this->result(self::OK, $status),
            default => $this->result(self::BROKEN, $status),
        };
    }

    private function result(string $state, ?int $status = null): array
    {
        return ['state' => $state, 'status' => $status];
    }

    /** HEAD first; anything that is not a success is asked once more with GET, since many servers mishandle HEAD. */
    /** @param array<int, string> $pin CURLOPT_RESOLVE entries for the addresses that passed the check */
    private function ask(string $url, array $pin): Response
    {
        $request = Http::withHeaders(['User-Agent' => 'HEL.ink-LinkCheck/1.0 (+'.public_url('').')', 'Accept' => '*/*'])
            ->timeout((int) config('shortener.monitor.timeout', 8))
            ->connectTimeout(5)
            // No 'stream' option: Guzzle then hands the request to PHP's stream handler, which ignores the curl options, so the
            // pin below would not be applied and the request would resolve the name again (DNS rebinding). The body is only
            // status-checked, so it goes to /dev/null: nothing of it is kept in memory or on disk, and the timeout bounds the transfer.
            // (CURLOPT_MAXFILESIZE would make any page that declares more than the limit look broken.)
            ->withOptions(['allow_redirects' => false, 'sink' => '/dev/null', 'curl' => [CURLOPT_RESOLVE => $pin]]);
        $response = $request->head($url);
        if ($response->status() >= 400) {
            $response = $request->get($url);
        }
        // The body is never read: close the connection instead of downloading the page.
        $response->toPsrResponse()->getBody()->close();

        return $response;
    }

    private function absolute(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($location, '//')) {
            return $b['scheme'].':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        return $origin.rtrim(dirname($b['path'] ?? '/'), '/').'/'.$location;
    }

    /** Whether the host has an address at all (an IP literal always does). Split out for tests. */
    protected function hostResolves(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        return (is_array($records) && $records !== []) || gethostbyname($host) !== $host;
    }
}
