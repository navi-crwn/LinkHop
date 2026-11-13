<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an answer out of search results with `X-Robots-Tag: noindex`, whatever it is: a redirect, a password page,
 * an ended or missing link, the preview pages. Used on the short-link routes (/l/{slug} and the old /{slug}), whose
 * answers are never a page of their own. A header the answer already set is kept.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }
}
