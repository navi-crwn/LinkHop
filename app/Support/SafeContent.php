<?php

namespace App\Support;

use App\Services\LinkSafety;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Render-time escaping for user content on public bio pages. Applied when
 * rendering (not only on save) so rows stored before this existed are safe.
 */
class SafeContent
{
    private static ?HtmlSanitizer $sanitizer = null;

    /**
     * Sanitize rich text / HTML blocks: keeps formatting tags, drops scripts,
     * event handlers, styles and non-http(s)/mailto/tel links.
     */
    public static function html(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->blockElement('form')
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'nofollow noreferrer noopener')
                ->forceAttribute('a', 'target', '_blank')
                ->withMaxInputLength(50000)
        );

        return self::$sanitizer->sanitize($html);
    }

    /**
     * Href for a user-supplied link; anything outside the allowed schemes
     * (javascript:, data:, ...) becomes "#".
     */
    public static function url(?string $url, array $schemes = LinkSafety::BIO_SCHEMES): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '#';
        }
        if (self::isSitePath($url)) {
            return $url;
        }
        $scheme = app(LinkSafety::class)->scheme($url);

        return $scheme !== null && in_array($scheme, $schemes, true) ? $url : '#';
    }

    /**
     * A same-site path such as "/storage/x.png". Browsers ignore tabs/newlines and
     * read "\" as "/", so "/\evil.com" or "/\t/evil.com" would leave the site.
     */
    public static function isSitePath(string $url): bool
    {
        $clean = preg_replace('/[\x00-\x20]+/', '', $url);

        return str_starts_with($clean, '/') && ! str_contains($clean, '\\') && ! str_starts_with($clean, '//');
    }

    /**
     * Custom CSS may not break out of its <style> element or load scripts.
     */
    public static function css(?string $css): string
    {
        $css = (string) $css;
        $css = str_replace('<', '', $css); // '<' is all a "</style>" breakout needs
        $css = preg_replace('/@import|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', '', $css);

        return $css;
    }
}
