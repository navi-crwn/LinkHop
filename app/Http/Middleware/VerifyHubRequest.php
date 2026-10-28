<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `hub.signed`: server-to-server calls from the hub (account export, purge on
 * account deletion), signed with the shared SSO secret (HOP_SSO_SECRET):
 *
 *   X-Hop-Timestamp: unix seconds (within hop.request_max_age)
 *   X-Hop-Signature: hex HMAC-SHA256(secret, "{ts}\n{METHOD}\n{/path}\n{sha256(body)}")
 */
class VerifyHubRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('hop-auth.secret');
        if (strlen($secret) < 32) {
            return response()->json(['ok' => false, 'error' => 'not_configured', 'message' => 'HOP_SSO_SECRET is not set.'], 503);
        }
        $ts = (string) $request->header('X-Hop-Timestamp', '');
        $sig = (string) $request->header('X-Hop-Signature', '');
        if (! ctype_digit($ts) || abs(time() - (int) $ts) > (int) config('hop.request_max_age', 120)) {
            return response()->json(['ok' => false, 'error' => 'stale', 'message' => 'Timestamp missing or too old.'], 401);
        }
        $expected = self::sign($secret, (int) $ts, $request->method(), '/'.ltrim($request->path(), '/'), $request->getContent());
        if ($sig === '' || ! hash_equals($expected, strtolower($sig))) {
            return response()->json(['ok' => false, 'error' => 'bad_signature', 'message' => 'Signature does not match.'], 401);
        }

        return $next($request);
    }

    public static function sign(string $secret, int $ts, string $method, string $path, string $body): string
    {
        return hash_hmac('sha256', $ts."\n".strtoupper($method)."\n".$path."\n".hash('sha256', $body), $secret);
    }
}
