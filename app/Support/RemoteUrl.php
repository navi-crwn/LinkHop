<?php

namespace App\Support;

/**
 * Addresses found inside another site's HTML (og:image, favicon, og:url) are often relative to that
 * page: "/img/og.png" or "//cdn.example/og.png". Taken as they are they would be requested from our own
 * host, so they are resolved against the page they came from before they are shown or saved.
 */
final class RemoteUrl
{
    public static function resolve(string $base, string $reference): string
    {
        $reference = trim(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5));
        if ($reference === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $reference)) {
            return $reference;
        }
        $b = parse_url($base);
        if (empty($b['scheme']) || empty($b['host'])) {
            return $reference;
        }
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($reference, '//')) {
            return $b['scheme'].':'.$reference;
        }
        if (str_starts_with($reference, '/')) {
            return $origin.$reference;
        }
        if ($reference[0] === '?' || $reference[0] === '#') {
            return $origin.($b['path'] ?? '/').$reference;
        }
        $dir = substr($b['path'] ?? '/', 0, strrpos($b['path'] ?? '/', '/') + 1);

        return $origin.$dir.$reference;
    }
}
