<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One HEL.ink account for every HEL.ink app.
 *
 * PixelHop (image hosting at p.hel.ink) has no accounts of its own: it sends
 * the visitor here, HEL.ink signs them in as usual (password, Google, 2FA,
 * Turnstile, email verification, password reset all live here), and this
 * controller hands back a short-lived signed ticket that PixelHop verifies
 * with the shared secret.
 *
 * Ticket: base64url(json payload) "." base64url(HMAC-SHA256(secret, first part)).
 * It expires after two minutes and carries the state PixelHop sent, which
 * PixelHop matches against a cookie, so a ticket can't be replayed elsewhere.
 */
class PixelHopSsoController extends Controller
{
    private const TICKET_TTL = 120;

    public function ticket(Request $request): RedirectResponse
    {
        $base = $this->baseUrl();
        $secret = (string) config('services.pixelhop.sso_secret');
        abort_if($base === null || strlen($secret) < 32, 404);

        $state = (string) $request->query('state', '');
        $returnTo = (string) $request->query('return_to', '');
        abort_unless(preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state) === 1, 400, 'Invalid state.');
        // Only ever send tickets to PixelHop's own callback.
        abort_unless($returnTo === $base.'/auth/helink/callback', 400, 'Invalid return address.');

        /** @var User $user */
        $user = $request->user();
        if ($user->isSuspended()) {
            return redirect()->route('suspended');
        }
        // Google already verified the address; everyone else confirms it first.
        if (! $user->hasVerifiedEmail() && ! $user->google_id) {
            return redirect()->route('verification.notice');
        }

        $now = time();
        $payload = [
            'sub' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'avatar' => $user->avatar ?: '',
            'admin' => $user->isSuperAdmin(),
            'state' => $state,
            'iat' => $now,
            'exp' => $now + self::TICKET_TTL,
            'jti' => bin2hex(random_bytes(16)),
        ];
        $body = $this->b64(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $ticket = $body.'.'.$this->b64(hash_hmac('sha256', $body, $secret, true));

        return redirect()->away($returnTo.'?'.http_build_query(['ticket' => $ticket]));
    }

    /** The "Images" item in the dashboard menu: open PixelHop, signed in. */
    public function open(): RedirectResponse
    {
        $base = $this->baseUrl();
        abort_if($base === null, 404);

        return redirect()->away($base.'/auth/helink?next=%2Fdashboard');
    }

    private function baseUrl(): ?string
    {
        $url = rtrim((string) config('services.pixelhop.url'), '/');

        return str_starts_with($url, 'https://') || str_starts_with($url, 'http://') ? $url : null;
    }

    private function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
