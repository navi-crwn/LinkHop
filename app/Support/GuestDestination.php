<?php

namespace App\Support;

/**
 * The destination of a guest link, normalized so the same place counts once: lower-case host without www, the path
 * without a trailing slash, and no query or fragment — a tracking parameter makes every victim's copy look new, and
 * a kit relies on exactly that. One rule for the log, the cap and the burst detection, so they cannot disagree.
 */
final class GuestDestination
{
    public static function normalize(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $host = preg_replace('/^www\./', '', strtolower(trim((string) $parts['host'], '[]'))) ?: '';
        if ($host === '') {
            return null;
        }
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        // A path with a percent-escape is kept as sent; a plain one is used as is. Case is kept: paths are
        // case-sensitive, and two victims of a kit share the exact path anyway.
        return $host.$path;
    }

    /** The registrable domain of a normalized destination, for grouping a kit that moves between paths. */
    public static function domain(?string $destination): ?string
    {
        if ($destination === null || $destination === '') {
            return null;
        }
        $host = explode('/', $destination, 2)[0];

        return PhishingShape::registrable($host);
    }
}
