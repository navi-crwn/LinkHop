<?php

namespace App\Http\Middleware;

use Closure;
use Hop\Bio\Support\BioEmbed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Per-request nonce: added to every <script> tag (Vite::cspNonce()) and @vite output.
        $nonce = Vite::useCspNonce();
        $response = $next($request);
        // Allow framing for the bio preview route
        $isPreviewRoute = $request->routeIs('bio.preview');
        // Public pages (hel.ink/l/, hel.ink/b/) are rendered on PUBLIC_URL while the built assets and uploads are
        // served from ASSET_URL (default APP_URL, link.hel.ink), so that origin must be allowed next to 'self'.
        $assets = self::assetOrigin();
        $assets = $assets !== '' ? ' '.$assets : '';
        // Nothing is loaded from a CDN: the libraries are self-hosted (public/vendor). Only a bio page's own optional
        // pixels (Google Analytics, Meta Pixel) need other hosts, and only when bio pages are switched on.
        $bio = bio_enabled();
        $pixelsScript = $bio ? ' https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net' : '';
        $pixelsImg = $bio ? ' https://www.google-analytics.com https://www.facebook.com' : '';
        $pixelsConnect = $bio ? ' https://www.google-analytics.com https://analytics.google.com https://www.facebook.com' : '';
        $csp = [
            "default-src 'self'",
            "script-src 'self'{$assets} https://hel.ink 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval' https: 'unsafe-inline' https://challenges.cloudflare.com https://static.cloudflareinsights.com{$pixelsScript}",
            "script-src-attr 'none'",
            "style-src 'self'{$assets} 'unsafe-inline' https://hel.ink",
            "img-src 'self'{$assets} data: https: blob: https://*.tile.openstreetmap.org{$pixelsImg}",
            "font-src 'self'{$assets} https://hel.ink data:",
            "connect-src 'self'{$assets} data: blob: https://challenges.cloudflare.com https://*.tile.openstreetmap.org{$pixelsConnect}",
            "frame-src 'self' https://challenges.cloudflare.com https://maps.google.com https://www.google.com https://youtube.com ".implode(' ', BioEmbed::FRAME_HOSTS),
            "object-src 'none'",
            "base-uri 'self'",
            // Logging out posts here, then hops hub -> each app's logout -> the home page (hel.ink).
            // form-action applies to every redirect after a form post, so every HOP host is allowed.
            "form-action 'self' ".self::hopOrigins(),
            $isPreviewRoute ? "frame-ancestors 'self'" : "frame-ancestors 'none'",
            'upgrade-insecure-requests',
            'report-uri '.route('csp.report', [], false),
        ];
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', $isPreviewRoute ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }

    /** The origin (scheme://host[:port]) the app's own files are served from: ASSET_URL, else APP_URL. */
    private static function assetOrigin(): string
    {
        $parts = parse_url((string) (config('app.asset_url') ?: config('app.url')));
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** Origins of the hub, every HOP app and the public host, space-separated, without duplicates. */
    private static function hopOrigins(): string
    {
        $urls = [hub_url(), public_url()];
        foreach ((array) config('hop.apps', []) as $app) {
            $urls[] = (string) ($app['url'] ?? '');
        }
        $origins = [];
        foreach ($urls as $url) {
            $parts = parse_url($url);
            if (! empty($parts['scheme']) && ! empty($parts['host'])) {
                $origins[] = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }

        // The chain also passes apps this list does not know (NoteHop): every https subdomain of the family's
        // domain (hub.hel.ink -> *.hel.ink) may be a step, like the chained logout itself allows.
        $hubParts = parse_url(hub_url());
        $hubHost = strtolower((string) ($hubParts['host'] ?? ''));
        if (($hubParts['scheme'] ?? '') === 'https' && substr_count($hubHost, '.') >= 2) {
            $origins[] = 'https://*.'.preg_replace('/^[^.]+\./', '', $hubHost);
        }

        return implode(' ', array_values(array_unique($origins)));
    }
}
