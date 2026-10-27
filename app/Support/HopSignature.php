<?php

namespace App\Support;

use App\Models\HopApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Signed server-to-server messages between HOP ID and the apps (HOP App
 * Protocol v1). Headers: X-HOP-App, X-HOP-Timestamp, X-HOP-Nonce, X-HOP-Actor,
 * X-HOP-Signature = base64url(HMAC-SHA256(secret,
 * "hop1|app|ts|nonce|METHOD|PATH|sha256hex(body)")). The secret is the
 * registered app's own; both directions use it.
 */
class HopSignature
{
    public const VERSION = 'hop1';

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function unb64(string $s): string|false
    {
        return base64_decode(strtr($s, '-_', '+/'), true);
    }

    public static function stringToSign(string $app, string $ts, string $nonce, string $method, string $path, string $body): string
    {
        return implode('|', [self::VERSION, $app, $ts, $nonce, strtoupper($method), $path, hash('sha256', $body)]);
    }

    /** Headers for a message HOP ID sends to the app (signed with the app's secret). */
    public static function headers(HopApp $app, string $method, string $path, string $body, string $actor = 'system'): array
    {
        $ts = (string) time();
        $nonce = self::b64(random_bytes(16));
        $sig = self::b64(hash_hmac('sha256', self::stringToSign('helink', $ts, $nonce, $method, $path, $body), $app->secrets()[0], true));

        return [
            'X-HOP-App' => 'helink',
            'X-HOP-Timestamp' => $ts,
            'X-HOP-Nonce' => $nonce,
            'X-HOP-Actor' => $actor,
            'X-HOP-Signature' => $sig,
        ];
    }

    /**
     * Checks a request an app sent to HOP ID. Returns the app, or an error code:
     * not_configured, stale_request, bad_signature, replay.
     */
    public static function verify(Request $request): HopApp|string
    {
        $slug = (string) $request->header('X-HOP-App', '');
        $app = preg_match('/^[a-z0-9-]{1,32}$/', $slug) ? HopApp::find_($slug) : null;
        if (! $app || ! $app->isConfigured()) {
            return 'not_configured';
        }
        $ts = (string) $request->header('X-HOP-Timestamp', '');
        $nonce = (string) $request->header('X-HOP-Nonce', '');
        $sig = (string) $request->header('X-HOP-Signature', '');
        if (! ctype_digit($ts) || abs(time() - (int) $ts) > (int) config('hop.request_max_age', 120)) {
            return 'stale_request';
        }
        if (strlen($nonce) < 16 || strlen($nonce) > 128) {
            return 'bad_signature';
        }
        // PATH is the request target with its query string, e.g. /api/hop/profiles?ids=1
        $base = self::stringToSign($slug, $ts, $nonce, $request->getMethod(), $request->getRequestUri(), (string) $request->getContent());
        $ok = false;
        foreach ($app->secrets() as $secret) {
            $expected = self::b64(hash_hmac('sha256', $base, $secret, true));
            if (hash_equals($expected, $sig)) {
                $ok = true;
                break;
            }
        }
        if (! $ok) {
            return 'bad_signature';
        }
        // Each nonce works once within the time window.
        if (! Cache::add('hop:nonce:'.$slug.':'.sha1($nonce), 1, (int) config('hop.request_max_age', 120) * 2)) {
            return 'replay';
        }

        return $app;
    }
}
