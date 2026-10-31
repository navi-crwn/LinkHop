<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page language: ?lang= (remembered in a cookie for a year), then the cookie, then
 * English. The browser's Accept-Language is not used, so English stays the default
 * (owner decision). Texts live in lang/{locale}.json; English is the key itself.
 */
class SetLocale
{
    /** The HOP family's eight languages. @var array<string, string> */
    public const LOCALES = [
        'en' => 'English', 'id' => 'Bahasa Indonesia', 'zh' => '中文', 'es' => 'Español',
        'ar' => 'العربية', 'pt' => 'Português', 'fr' => 'Français', 'ja' => '日本語',
    ];

    public const COOKIE = 'hop_lang';

    public function handle(Request $request, Closure $next): Response
    {
        $query = (string) $request->query('lang', '');
        $locale = isset(self::LOCALES[$query]) ? $query : (string) $request->cookie(self::COOKIE, 'en');
        if (! isset(self::LOCALES[$locale])) {
            $locale = 'en';
        }
        app()->setLocale($locale);
        $response = $next($request);
        if (isset(self::LOCALES[$query])) {
            $response->headers->setCookie(cookie()->forever(self::COOKIE, $query, null, null, $request->isSecure(), false, false, 'lax'));
        }

        return $response;
    }
}
