<?php

namespace App\Support;

use App\Http\Controllers\ChangelogController;
use Illuminate\Support\Facades\Route;

/**
 * The Feedback link: it opens the HOP ID form in a new tab and nothing else. The address is built from the hub
 * address this app already uses for the family announcement (hop.hub_url), the name of the screen (never the real
 * address) and the running version. Without a hub address there is no link, anywhere.
 *
 * Contract: {hub}/feedback?app=linkhop&from=<screen>&v=<version>; from is A-Za-z0-9._:/{}- up to 80, v is A-Za-z0-9.+- up to 24.
 */
final class Feedback
{
    public const APP = 'linkhop';

    private static ?string $version = null;

    /** The hub address without a trailing slash, or '' when it is empty or not an http(s) address. */
    public static function hub(): string
    {
        $hub = rtrim((string) config('hop.hub_url'), '/');
        $scheme = strtolower((string) parse_url($hub, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && parse_url($hub, PHP_URL_HOST) ? $hub : '';
    }

    public static function enabled(): bool
    {
        return self::hub() !== '';
    }

    /** The hub's privacy policy, or '' without a hub address. */
    public static function privacyUrl(): string
    {
        return self::hub() === '' ? '' : self::hub().'/privacy';
    }

    /** The name of the screen being shown: the route name, else the route template (never the real address). */
    public static function screen(): string
    {
        $route = Route::current();
        $name = $route?->getName() ?: ($route ? $route->uri() : '');

        return substr((string) preg_replace('/[^A-Za-z0-9._:\/{}-]/', '', (string) $name), 0, 80);
    }

    public static function version(): string
    {
        if (self::$version === null) {
            try {
                $raw = (string) (ChangelogController::history()['sources']['LinkHop'] ?? '');
            } catch (\Throwable) {
                $raw = '';
            }
            self::$version = substr((string) preg_replace('/[^A-Za-z0-9.+-]/', '', $raw), 0, 24);
        }

        return self::$version;
    }

    /** {hub}/feedback?app=linkhop&from=<screen>&v=<version>, or '' (no link) without a usable hub address. */
    public static function url(): string
    {
        if (! self::enabled()) {
            return '';
        }
        $query = ['app' => self::APP];
        if (($from = self::screen()) !== '') {
            $query['from'] = $from;
        }
        if (($v = self::version()) !== '') {
            $query['v'] = $v;
        }

        return self::hub().'/feedback?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
