<?php

namespace App\Support;

/**
 * CSP violation reports are logged for debugging only. Report URLs can carry secrets
 * (password reset tokens in the path, signed URL signatures and emails in the query),
 * so only the fields we need are kept and URLs are reduced to a redacted path.
 */
class CspReport
{
    private const FIELDS = ['document-uri', 'violated-directive', 'blocked-uri', 'source-file', 'line-number', 'script-sample'];

    private const URL_FIELDS = ['document-uri', 'blocked-uri', 'source-file'];

    public static function sanitize(array $report): array
    {
        $report = array_intersect_key($report, array_flip(self::FIELDS));
        foreach (self::URL_FIELDS as $field) {
            if (isset($report[$field]) && is_string($report[$field])) {
                $report[$field] = self::url($report[$field]);
            }
        }
        if (isset($report['script-sample']) && is_string($report['script-sample'])) {
            $report['script-sample'] = mb_substr($report['script-sample'], 0, 80);
        }

        return $report;
    }

    public static function url(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            // 'inline', 'eval', 'self' and similar keywords.
            return preg_match('/^[a-z-]+$/i', $url) ? $url : '[redacted]';
        }
        $path = $parts['path'] ?? '';
        // Long random-looking segments are tokens, hashes or ids: never log them.
        $path = preg_replace('#/[A-Za-z0-9_\-]{20,}(?=/|$)#', '/[token]', $path);
        $path = preg_replace('#^/(reset-password|verify-email)/.*$#', '/$1/[token]', $path);

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').$path;
    }
}
