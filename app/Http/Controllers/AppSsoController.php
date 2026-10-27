<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One HEL.ink account for every HEL.ink app.
 *
 * The apps (PixelHop at p.hel.ink, NoteHop at note.hel.ink) have no sign-in
 * of their own: they send the visitor here, HEL.ink signs them in as usual
 * (password, Google, 2FA, Turnstile, email verification, password reset all
 * live here), and this controller hands back a short-lived signed ticket that
 * the app verifies with its own shared secret.
 *
 * Ticket: base64url(json payload) "." base64url(HMAC-SHA256(secret, first part)).
 * It expires after two minutes, names the app it is for (aud) and carries the
 * state the app sent, which the app matches against a cookie, so a ticket
 * can't be replayed elsewhere. Each app has its own secret, so a ticket for
 * one app is useless at another.
 */
class AppSsoController extends Controller
{
    private const TICKET_TTL = 120;

    /**
     * The apps: where their settings live, where tickets go, where a fresh
     * sign-in round trip starts, and the pages "open" can land on.
     */
    private const APPS = [
        'pixelhop' => [
            'name' => 'PixelHop',
            'config' => 'services.pixelhop',
            'callback' => '/auth/helink/callback',
            'start' => '/auth/helink',
            'pages' => ['default' => '/dashboard', 'upload' => '/'],
            'continue' => 'upload',
            'route' => 'images.open',
            'ticket' => 'sso.pixelhop',
        ],
        'notehop' => [
            'name' => 'NoteHop',
            'config' => 'services.notehop',
            'callback' => '/auth/sso/callback',
            'start' => '/auth/sso',
            'pages' => ['default' => '/dashboard', 'new' => '/'],
            'continue' => 'default',
            'route' => 'notes.open',
            'ticket' => 'sso.notehop',
        ],
    ];

    /** GET /sso/{app}: sign the current user in to the app. */
    public function ticket(Request $request, string $app): RedirectResponse
    {
        [$state, $returnTo, $secret] = $this->checked($request, $app);

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
            'aud' => $app,
            'email' => $user->email,
            'name' => $user->name,
            'avatar' => $user->avatar ?: '',
            'admin' => $user->isSuperAdmin(),
            'state' => $state,
            'iat' => $now,
            'exp' => $now + self::TICKET_TTL,
            'jti' => bin2hex(random_bytes(16)),
        ];
        $body = self::b64(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $ticket = $body.'.'.self::b64(hash_hmac('sha256', $body, $secret, true));

        return redirect()->away($returnTo.'?'.http_build_query(['ticket' => $ticket]));
    }

    /**
     * GET /sso/{app}/google: the app's "Continue with Google" button. Signed
     * in already: straight on to the ticket. Otherwise Google first (new
     * accounts are made on the spot, Google has verified the address), then
     * back to the ticket step through Laravel's intended URL, so the visitor
     * ends up in the app and not on HEL.ink's dashboard or onboarding.
     */
    public function viaGoogle(Request $request, string $app): RedirectResponse
    {
        [$state, $returnTo] = $this->checked($request, $app);
        abort_if(! config('services.google.client_id'), 404);
        $ticket = route(self::APPS[$app]['ticket'], ['state' => $state, 'return_to' => $returnTo]);
        if ($request->user()) {
            return redirect()->to($ticket);
        }
        $request->session()->put('url.intended', $ticket);

        return redirect()->route('auth.google', ['from_app' => 1]);
    }

    /**
     * The state and return address an app sent, checked, and its secret.
     *
     * @return array{string, string, string}
     */
    private function checked(Request $request, string $app): array
    {
        $base = self::baseUrl($app);
        $secret = (string) config(self::APPS[$app]['config'].'.sso_secret');
        abort_if($base === null || strlen($secret) < 32, 404);
        $state = (string) $request->query('state', '');
        $returnTo = (string) $request->query('return_to', '');
        abort_unless(preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state) === 1, 400, 'Invalid state.');
        // Only ever send tickets to the app's own callback.
        abort_unless($returnTo === $base.self::APPS[$app]['callback'], 400, 'Invalid return address.');

        return [$state, $returnTo, $secret];
    }

    /**
     * Menu items ("Images", "Notes"): open the app, signed in. ?to= picks one
     * of the app's pages; anything else lands on its dashboard.
     */
    public function open(Request $request, string $app): RedirectResponse
    {
        $base = self::baseUrl($app);
        abort_if($base === null, 404);
        $pages = self::APPS[$app]['pages'];
        $page = $pages[(string) $request->query('to')] ?? $pages['default'];

        return redirect()->away($base.self::APPS[$app]['start'].'?next='.rawurlencode($page));
    }

    /**
     * The app the visitor was signing in to, while they register, verify
     * their email or finish onboarding (Laravel keeps the /sso/{app} address
     * as the "intended" URL meanwhile), or null.
     */
    public static function cameFrom(Request $request): ?string
    {
        $intended = (string) $request->session()->get('url.intended', '');
        foreach (array_keys(self::APPS) as $app) {
            if (str_contains($intended, '/sso/'.$app) && self::baseUrl($app) !== null) {
                return $app;
            }
        }

        return null;
    }

    /**
     * Where to send someone who signed up on their way to an app. A fresh
     * sign-in round trip is used instead of the old ticket URL, whose state
     * cookie in the app may have expired while they read the email.
     */
    public static function continueTo(Request $request, string $app): RedirectResponse
    {
        $request->session()->forget('url.intended');

        return redirect()->to(self::continueUrl($app));
    }

    /** The app's display name ("Continue to NoteHop"). */
    public static function name(string $app): string
    {
        return self::APPS[$app]['name'];
    }

    /** The continue link for an app, for views. */
    public static function continueUrl(string $app): string
    {
        return route(self::APPS[$app]['route'], ['to' => self::APPS[$app]['continue']]);
    }

    /**
     * Origins of the configured apps, for the CSP form-action list: after the
     * sign-in or 2FA form, the browser follows a redirect to the app's
     * callback, and form-action also covers redirects after a form post.
     *
     * @return list<string>
     */
    public static function origins(): array
    {
        $out = [];
        foreach (array_keys(self::APPS) as $app) {
            $base = self::baseUrl($app);
            $p = $base === null ? false : parse_url($base);
            if ($p && isset($p['scheme'], $p['host'])) {
                $out[] = $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
            }
        }

        return array_values(array_unique($out));
    }

    private static function baseUrl(string $app): ?string
    {
        $url = rtrim((string) config(self::APPS[$app]['config'].'.url'), '/');

        return str_starts_with($url, 'https://') || str_starts_with($url, 'http://') ? $url : null;
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
