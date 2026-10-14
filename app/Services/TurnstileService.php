<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    public function verify(?string $token, ?string $ip = null): bool
    {
        $secret = config('services.turnstile.secret');
        if (! $secret) {
            // Unconfigured: allowed while developing, refused in production so a
            // missing key never silently disables bot protection.
            if (app()->isProduction()) {
                Log::critical('TURNSTILE_SECRET_KEY is not set; rejecting protected form submissions.');

                return false;
            }

            return true;
        }
        if (blank($token)) {
            return false;
        }
        try {
            $response = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip, // real visitor IP (TrustProxies trusts Cloudflare)
            ]);
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification failed', ['error' => $e->getMessage()]);

            return false;
        }

        return $response->ok() && (bool) data_get($response->json(), 'success', false);
    }
}
