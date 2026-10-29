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
        // Public pages (hel.ink/l/...) are served at PUBLIC_URL while the built assets and uploads
        // live at ASSET_URL (link.hel.ink), so that origin must be allowed next to 'self'.
        $assets = rtrim((string) config('app.asset_url'), '/');
        $assets = $assets !== '' ? ' '.$assets : '';
        $csp = [
            "default-src 'self'",
            "script-src 'self'{$assets} 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval' https: 'unsafe-inline' https://challenges.cloudflare.com https://fonts.bunny.net https://fonts.googleapis.com https://cdn.jsdelivr.net https://code.jquery.com https://unpkg.com https://cdn.tailwindcss.com https://static.cloudflareinsights.com https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net",
            "script-src-attr 'none'",
            "style-src 'self'{$assets} 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com",
            "img-src 'self' data: https: blob: https://flagcdn.com https://*.tile.openstreetmap.org https://www.google-analytics.com https://www.facebook.com",
            "font-src 'self'{$assets} https://fonts.bunny.net https://fonts.gstatic.com data:",
            "connect-src 'self'{$assets} data: blob: https://challenges.cloudflare.com https://raw.githubusercontent.com https://*.tile.openstreetmap.org https://cdn.jsdelivr.net https://unpkg.com https://www.google-analytics.com https://analytics.google.com https://www.facebook.com",
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

        return implode(' ', array_values(array_unique($origins)));
    }
}
