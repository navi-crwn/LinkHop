<?php

namespace App\Support;

use Hop\Safety\LinkSafety;

/**
 * The three limits a link can carry (a click limit, a start time and a fallback address) and the checks that
 * go with them, shared by the dashboard form and the API so both say the same thing.
 */
class LinkLimits
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'max_clicks' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'starts_at' => ['nullable', 'date'],
            'fallback_url' => ['nullable', 'url', 'max:2000'],
        ];
    }

    /** Why a fallback address cannot be used, or null when it is fine. */
    public static function fallbackProblem(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }
        if (self::pointsAtShortLinks($url)) {
            return __('The fallback address cannot be another short link: it could send visitors in a circle.');
        }

        return app(LinkSafety::class)->rejectionReason($url);
    }

    /** True for an address on this site's short-link path, in any case. */
    public static function pointsAtShortLinks(string $url): bool
    {
        $parts = parse_url(trim($url));
        $ours = strtolower((string) parse_url(public_url(''), PHP_URL_HOST));
        $host = strtolower((string) ($parts['host'] ?? ''));

        return $ours !== '' && str_replace('www.', '', $host) === str_replace('www.', '', $ours)
            && str_starts_with(strtolower($parts['path'] ?? ''), '/l/');
    }

    /** Expiry has to come after the start. */
    public static function orderProblem(mixed $startsAt, mixed $expiresAt): ?string
    {
        if (blank($startsAt) || blank($expiresAt) || is_array($startsAt) || is_array($expiresAt)) {
            return null;
        }

        return strtotime((string) $expiresAt) <= strtotime((string) $startsAt)
            ? __('The end date has to come after the start date.') : null;
    }
}
