<?php

namespace App\Http\Middleware;

use App\Support\HopSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * `hub.signed`: server-to-server calls from the hub (account export, purge on
 * account deletion, account events), signed with the shared SSO secret
 * (HOP_SSO_SECRET) using either:
 *   1) hex HMAC-SHA256(secret, "{ts}\n{METHOD}\n{/path}\n{sha256(body)}"), or
 *   2) HOP-1 base64url HMAC-SHA256(secret, "hop1|{app}|{ts}|{nonce}|{METHOD}|{/path}|{sha256(body)}").
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
        $maxAge = (int) config('hop.request_max_age', 120);
        if (! ctype_digit($ts) || abs(time() - (int) $ts) > $maxAge) {
            return response()->json(['ok' => false, 'error' => 'stale', 'message' => 'Timestamp missing or too old.'], 401);
        }
        $path = '/'.ltrim($request->path(), '/');
        $body = (string) $request->getContent();
        $nonce = (string) $request->header('X-Hop-Nonce', '');
        if ($nonce !== '') {
            if (strlen($nonce) < 16 || strlen($nonce) > 128) {
                return response()->json(['ok' => false, 'error' => 'bad_signature', 'message' => 'Invalid nonce.'], 401);
            }
            $app = (string) $request->header('X-Hop-App', 'helink');
            $base = HopSignature::stringToSign($app, $ts, $nonce, $request->method(), $request->getRequestUri(), $body);
            $expectedHop1 = HopSignature::b64(hash_hmac('sha256', $base, $secret, true));
            if ($sig === '' || ! hash_equals($expectedHop1, $sig)) {
                return response()->json(['ok' => false, 'error' => 'bad_signature', 'message' => 'Signature does not match.'], 401);
            }
            if (! Cache::add('hop:nonce:'.$app.':'.sha1($nonce), 1, $maxAge * 2)) {
                return response()->json(['ok' => false, 'error' => 'replay', 'message' => 'Nonce already used.'], 401);
            }

            return $next($request);
        }
        $expected = self::sign($secret, (int) $ts, $request->method(), $path, $body);
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
