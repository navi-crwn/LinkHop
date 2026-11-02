<?php

namespace App\Http\Middleware;

use Closure;
use Hop\Ui\Locales;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page language: ?lang= (remembered in a cookie for a year), then the cookie, then
 * English. The browser's Accept-Language is not used, so English stays the default
 * (owner decision). Texts live in lang/{locale}.json; English is the key itself.
 * The nine languages come from hop/ui (Hop\Ui\Locales); a stored `zh` or `pt` means
 * `zh-CN` or `pt-BR`, and a retired language such as `ar` falls back to English.
 */
class SetLocale
{
    public const COOKIE = 'hop_lang';

    public function handle(Request $request, Closure $next): Response
    {
        $requested = Locales::resolve((string) $request->query('lang', ''));
        $locale = $requested ?? Locales::normalize((string) $request->cookie(self::COOKIE, Locales::DEFAULT));
        app()->setLocale($locale);
        $response = $next($request);
        if ($requested !== null) {
            $response->headers->setCookie(cookie()->forever(self::COOKIE, $requested, null, null, $request->isSecure(), false, false, 'lax'));
        }

        return $response;
    }
}
