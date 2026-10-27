<?php

namespace App\Http\Middleware;

use App\Models\HopApp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After hop.signed: only an app registered with the `admin_client`
 * capability (Imperium) may use the admin endpoints.
 */
class RequireHopAdminClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $app = $request->attributes->get('hop_app');
        if (! $app instanceof HopApp || ! $app->can('admin_client')) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        return $next($request);
    }
}
