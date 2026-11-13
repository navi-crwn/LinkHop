<?php

namespace App\Support;

use Hop\Ui\Locales;
use Illuminate\Http\Request;

/**
 * What search engines read on LinkHop's public pages (APP_URL, link.hel.ink): one canonical address per page and
 * language, the language alternates and the structured data of the tool pages. The language of a page is chosen by
 * ?lang= (SetLocale; it wins over the cookie), so ?lang={code} is the address of a page in one language. The family
 * rule (train 4, KONTRAK-SEO-BAHASA, as amended 2026-11-13): English is the default language and lives at the bare
 * address; canonical = APP_URL + path, plus ?lang= only when the request carried one of the eight other codes; nine
 * alternates (en and x-default on the bare address, the rest ?lang={code}). Never built from the request host.
 */
final class Seo
{
    /** The public address of a path, in one language when $locale is one of the eight non-English codes, without any other query. */
    public static function url(string $path = '', ?string $locale = null): string
    {
        $url = rtrim((string) config('app.url'), '/').'/'.trim($path, '/');
        $locale = Locales::resolve($locale);

        return $locale === null || $locale === Locales::DEFAULT ? $url : $url.'?lang='.$locale;
    }

    /** The canonical address of the page being served: its path, plus ?lang= when the request asked for a language other than English. */
    public static function canonical(Request $request): string
    {
        return self::url($request->path(), is_string($request->query('lang')) ? $request->query('lang') : null);
    }

    /** @return array<string, string> hreflang => address: the nine languages (en on the bare address), then x-default */
    public static function alternates(string $path = ''): array
    {
        $out = [];
        foreach (Locales::codes() as $code) {
            $out[$code] = self::url($path, $code);
        }
        $out['x-default'] = self::url($path);

        return $out;
    }

    /**
     * JSON-LD for a page that is a tool (the home page's shortener, the QR maker).
     *
     * @return array<string, mixed>
     */
    public static function webApplication(string $name, string $description, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'applicationCategory' => 'UtilitiesApplication',
            'operatingSystem' => 'Any',
            'browserRequirements' => 'Requires JavaScript',
            'isAccessibleForFree' => true,
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'inLanguage' => Locales::codes(),
            'image' => route('brand.logo'),
        ];
    }

    /** The same data as a script tag body: safe inside <script>, readable non-ASCII. */
    public static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
