<?php

namespace App\Http\Middleware;

use Closure;
use Hop\Ui\Locales;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page language: ?lang= (remembered in a cookie for a year), then the cookie, then
 * English. The browser's Accept-Language is not used, so English stays the default
 * (owner decision). Texts live in lang/{locale}.json; English is the key itself.
 * The nine languages come from hop/ui (Hop\Ui\Locales); a stored `zh` or `pt` means
 * `zh-CN` or `pt-BR`, and a retired language such as `ar` falls back to English.
 *
 * hop_lang is the family's language cookie (HOP preference contract, train 4): plain text (not encrypted, see
 * bootstrap/app.php) so scripts and the Go apps read it too. On hel.ink and *.hel.ink it is set for
 * Domain=.hel.ink, so one choice holds in every app; elsewhere (localhost, a self-hosted copy) it stays host-only.
 * A browser that still holds this host's old host-only hop_lang sends it before the domain one, so the old one would
 * win: once per browser (no hop_pref=2 yet) the old cookie is expired, its value moved to the domain cookie, and
 * hop_pref=2 set. Other hosts are not migrated.
 */
class SetLocale
{
    public const COOKIE = 'hop_lang';

    /** Set once this browser's host-only hop_lang has been moved to the family domain. */
    public const MIGRATED = 'hop_pref';

    private const YEAR = 31536000; // seconds

    public function handle(Request $request, Closure $next): Response
    {
        $requested = self::apply($request);
        $response = $next($request);
        $domain = self::familyDomain($request);
        $secure = $domain !== null || $request->isSecure();
        if ($domain !== null && $request->cookie(self::MIGRATED) !== '2') {
            $response->headers->setCookie(Cookie::create(self::COOKIE, '', 1, '/', null, $secure, false, false, Cookie::SAMESITE_LAX));
            $carried = Locales::resolve(self::cookieLanguage($request));
            if ($carried !== null && $requested === null) {
                $response->headers->setCookie(self::make(self::COOKIE, $carried, $domain, $secure));
            }
            $response->headers->setCookie(self::make(self::MIGRATED, '2', null, $secure));
        }
        if ($requested !== null) {
            $response->headers->setCookie(self::make(self::COOKIE, $requested, $domain, $secure));
        }

        return $response;
    }

    /** Sets the page language from the request and returns the one ?lang= asked for, if any. Needs no database. */
    public static function apply(Request $request): ?string
    {
        $requested = Locales::resolve(is_string($request->query('lang')) ? $request->query('lang') : '');
        app()->setLocale($requested ?? Locales::normalize(self::cookieLanguage($request) ?? Locales::DEFAULT));

        return $requested;
    }

    /**
     * The hop_lang the browser sent. A browser that still holds this host's old host-only cookie next to the newer
     * .hel.ink one sends both, oldest first, and PHP keeps the first: so when the raw Cookie header names hop_lang more
     * than once, the last one counts (family contract, amended after the LinkHop audit). With one, nothing changes.
     */
    public static function cookieLanguage(Request $request): ?string
    {
        $values = [];
        foreach (explode(';', (string) $request->headers->get('Cookie', '')) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, null);
            if ($value !== null && trim($name) === self::COOKIE) {
                $values[] = urldecode(trim($value));
            }
        }
        if (count($values) > 1) {
            return end($values);
        }
        $value = $request->cookie(self::COOKIE);

        return is_string($value) ? $value : null;
    }

    /** `.hel.ink` when the request's host is hel.ink or one of its subdomains, null (host-only) otherwise. */
    public static function familyDomain(Request $request): ?string
    {
        $host = strtolower($request->getHost());

        return $host === 'hel.ink' || str_ends_with($host, '.hel.ink') ? '.hel.ink' : null;
    }

    private static function make(string $name, string $value, ?string $domain, bool $secure): Cookie
    {
        return Cookie::create($name, $value, time() + self::YEAR, '/', $domain, $secure, false, false, Cookie::SAMESITE_LAX);
    }
}
