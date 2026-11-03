<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pages shown to a signed-in person must not be kept by the browser: after a
 * sign-out (here or at the hub, through the logout chain) the Back button would
 * otherwise show the page again from the cache. Laravel's default for a page
 * without a cache policy is `no-cache, private`, which still allows storing it.
 * Responses that set their own policy (public assets, `max-age`) are left alone.
 */
class NoStoreWhenSignedIn
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() === null
            || $response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse
            || $response->headers->hasCacheControlDirective('public')
            || $response->headers->hasCacheControlDirective('max-age')
            || $response->headers->hasCacheControlDirective('no-store')) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
