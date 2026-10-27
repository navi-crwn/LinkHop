<?php

namespace App\Http\Controllers;

use App\Models\HopApp;
use App\Models\User;
use App\Services\LoginRecorder;
use App\Support\HopSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One HOP ID for every app in the HOP family.
 *
 * The apps (rows in `apps`: PixelHop, NoteHop, …) have no sign-in of their
 * own: they send the visitor here, HOP ID signs them in as usual (password,
 * Google, 2FA, Turnstile, email verification, password reset all live here),
 * and this controller hands back a short-lived signed ticket that the app
 * verifies with its own shared secret.
 *
 * Ticket: base64url(json payload) "." base64url(HMAC-SHA256(secret, first part)).
 * It expires after two minutes, names the app it is for (aud) and carries the
 * state the app sent, which the app matches against a cookie, so a ticket
 * can't be replayed elsewhere. Each app has its own secret, so a ticket for
 * one app is useless at another. Version 2 adds the HOP profile (username,
 * roles, profile_version, …); the v1 fields are unchanged.
 */
class AppSsoController extends Controller
{
    /** GET /sso/{app}: sign the current user in to the app. */
    public function ticket(Request $request, string $app): RedirectResponse
    {
        [$hopApp, $state, $returnTo] = $this->checked($request, $app);

        /** @var User $user */
        $user = $request->user();
        if ($user->isSuspended()) {
            return redirect()->route('suspended');
        }
        // Google already verified the address; everyone else confirms it first.
        if (! $user->hasVerifiedEmail() && ! $user->google_id) {
            return redirect()->route('verification.notice');
        }
        if ($hopApp->status === HopApp::STATUS_MAINTENANCE) {
            return redirect()->route('dashboard')->with('error', $hopApp->name.' is under maintenance right now. Please try again later.');
        }

        $now = time();
        $payload = $user->hopProfile() + [
            'aud' => $hopApp->slug,
            'admin' => $user->isAdminOf($hopApp->slug),
            'state' => $state,
            'iat' => $now,
            'exp' => $now + (int) config('hop.ticket_ttl', 120),
            'jti' => bin2hex(random_bytes(16)),
            'v' => 2,
        ];
        $body = HopSignature::b64(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $ticket = $body.'.'.HopSignature::b64(hash_hmac('sha256', $body, $hopApp->secrets()[0], true));
        app(LoginRecorder::class)->record($user, $request->ip(), $request->userAgent(), $hopApp->name);

        return redirect()->away($returnTo.'?'.http_build_query(['ticket' => $ticket]));
    }

    /**
     * GET /sso/{app}/google: the app's "Continue with Google" button. Signed
     * in already: straight on to the ticket. Otherwise Google first (new
     * accounts are made on the spot, Google has verified the address), then
     * back to the ticket step through Laravel's intended URL, so the visitor
     * ends up in the app and not on the dashboard or onboarding.
     */
    public function viaGoogle(Request $request, string $app): RedirectResponse
    {
        [$hopApp, $state, $returnTo] = $this->checked($request, $app);
        abort_if(! config('services.google.client_id'), 404);
        $ticket = route('sso.ticket', ['app' => $hopApp->slug, 'state' => $state, 'return_to' => $returnTo]);
        if ($request->user()) {
            return redirect()->to($ticket);
        }
        $request->session()->put('url.intended', $ticket);

        return redirect()->route('auth.google', ['from_app' => 1]);
    }

    /**
     * The app, and the state and return address it sent, checked.
     *
     * @return array{HopApp, string, string}
     */
    private function checked(Request $request, string $slug): array
    {
        $app = HopApp::find_($slug);
        abort_if(! $app || ! $app->isConfigured(), 404);
        $state = (string) $request->query('state', '');
        $returnTo = (string) $request->query('return_to', '');
        abort_unless(preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state) === 1, 400, 'Invalid state.');
        // Only ever send tickets to the app's own callback.
        abort_unless($returnTo === $app->callbackUrl(), 400, 'Invalid return address.');

        return [$app, $state, $returnTo];
    }

    /**
     * Menu items ("Images", "Notes"): open the app, signed in. ?to= picks one
     * of the app's pages; anything else lands on its default page.
     */
    public function open(Request $request, string $app): RedirectResponse
    {
        $hopApp = HopApp::find_($app);
        abort_if(! $hopApp || ! $hopApp->isConfigured(), 404);

        return redirect()->away($hopApp->baseUrl().$hopApp->start_path.'?next='.rawurlencode($hopApp->page($request->query('to'))));
    }

    /**
     * The app the visitor was signing in to, while they register, verify
     * their email or finish onboarding (Laravel keeps the /sso/{app} address
     * as the "intended" URL meanwhile), or null.
     */
    public static function cameFrom(Request $request): ?HopApp
    {
        $intended = (string) $request->session()->get('url.intended', '');
        if (preg_match('#/sso/([a-z0-9-]{1,32})(?:[/?]|$)#', $intended, $m) !== 1) {
            return null;
        }
        $app = HopApp::find_($m[1]);

        return $app && $app->isConfigured() ? $app : null;
    }

    /**
     * Where to send someone who signed up on their way to an app. A fresh
     * sign-in round trip is used instead of the old ticket URL, whose state
     * cookie in the app may have expired while they read the email.
     */
    public static function continueTo(Request $request, HopApp $app): RedirectResponse
    {
        $request->session()->forget('url.intended');

        return redirect()->to(self::continueUrl($app));
    }

    /** The continue link for an app, for views. */
    public static function continueUrl(HopApp $app): string
    {
        return route('apps.open', ['app' => $app->slug, 'to' => $app->continue_page]);
    }

    /**
     * Origins of the configured apps, for the CSP form-action list: after the
     * sign-in or 2FA form, the browser follows a redirect to the app's
     * callback, and form-action also covers redirects after a form post.
     * Hidden apps (Imperium) sign in too, so they are included.
     *
     * @return list<string>
     */
    public static function origins(): array
    {
        $out = [];
        try {
            $apps = HopApp::registered()->get()->filter->isConfigured();
        } catch (\Throwable) {
            return [];
        }
        foreach ($apps as $app) {
            if ($o = $app->origin()) {
                $out[] = $o;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * The configured apps, for menus. Empty when the table does not exist yet
     * (first deploy before migrations).
     *
     * @return list<HopApp>
     */
    public static function apps(): array
    {
        try {
            return HopApp::active()->get()->filter->isConfigured()->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
