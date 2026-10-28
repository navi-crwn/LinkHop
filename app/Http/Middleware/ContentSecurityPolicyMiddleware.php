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
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com https://fonts.bunny.net https://fonts.googleapis.com https://cdn.jsdelivr.net https://code.jquery.com https://unpkg.com https://cdn.tailwindcss.com https://static.cloudflareinsights.com https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com",
            "img-src 'self' data: https: blob: https://flagcdn.com https://*.tile.openstreetmap.org https://www.google-analytics.com https://www.facebook.com",
            "font-src 'self' https://fonts.bunny.net https://fonts.gstatic.com data:",
            "connect-src 'self' data: blob: https://challenges.cloudflare.com https://raw.githubusercontent.com https://*.tile.openstreetmap.org https://cdn.jsdelivr.net https://unpkg.com https://www.google-analytics.com https://analytics.google.com https://www.facebook.com",
            "frame-src 'self' https://challenges.cloudflare.com https://maps.google.com https://www.google.com https://youtube.com ".implode(' ', BioEmbed::FRAME_HOSTS),
            "object-src 'none'",
            "base-uri 'self'",
            // Logging out posts here and ends at the hub; form-action applies to that redirect too.
            "form-action 'self' ".hub_url(),
            $isPreviewRoute ? "frame-ancestors 'self'" : "frame-ancestors 'none'",
            'upgrade-insecure-requests',
        ];
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        // Strict nonce-based policy in report-only mode: browsers report what it would
        // block (inline on* handlers, injected scripts) to /csp-report without breaking
        // pages. Switch to enforcing once the reports are clean.
        $response->headers->set('Content-Security-Policy-Report-Only', implode('; ', [
            "script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval' https: 'unsafe-inline'",
            "script-src-attr 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            'report-uri '.route('csp.report', [], false),
        ]));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', $isPreviewRoute ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
