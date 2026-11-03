<?php

namespace App\Services;

use Hop\Safety\UrlGuard;
use RuntimeException;

/**
 * The curl option that makes an outbound request connect to exactly the addresses UrlGuard checked, so a host
 * that answers differently the second time (DNS rebinding) cannot lead the fetch to an internal address.
 * Callers keep redirects off, or check every hop the same way.
 */
final class PinnedFetch
{
    /**
     * @return array<int, array<int, string>> for the `curl` option of Guzzle or Http::withOptions()
     *
     * @throws RuntimeException when the address is not one we may fetch
     */
    public static function curl(string $url): array
    {
        $guard = app(UrlGuard::class);
        $target = $guard->resolve($url);
        if ($target === null) {
            throw new RuntimeException('The address is not allowed.');
        }

        return [CURLOPT_RESOLVE => $guard->curlResolve($target)];
    }
}
