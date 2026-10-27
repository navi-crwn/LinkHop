<?php

namespace App\Http\Middleware;

use App\Support\HopSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only lets in requests signed by a registered HOP app; puts the app on the request. */
class VerifyHopSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $app = HopSignature::verify($request);
        if (is_string($app)) {
            return response()->json(['ok' => false, 'error' => $app], $app === 'not_configured' ? 404 : 401);
        }
        $request->attributes->set('hop_app', $app);

        return $next($request);
    }
}
