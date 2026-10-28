<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sign-in and sign-out, both handled by the hub (HOP ID). hop/auth does the
 * SSO round trip (/auth/sso → hub /sso/linkhop → /auth/sso/callback); these
 * routes only keep the familiar /login and /logout addresses working.
 */
class SessionController extends Controller
{
    /** GET /: the dashboard, or the hub's sign-in for a guest. */
    public function home(Request $request): RedirectResponse
    {
        return $request->user() ? redirect()->route('dashboard') : $this->login($request);
    }

    /** GET /login: start the SSO round trip, coming back to ?next= (a local path) or the dashboard. */
    public function login(Request $request): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }
        $next = (string) $request->query('next', $request->session()->pull('url.intended', ''));
        if ($next !== '' && str_starts_with($next, config('app.url'))) {
            $next = substr($next, strlen(config('app.url')));
        }

        return redirect()->route('hop.sso.start', $next !== '' ? ['next' => $next] : []);
    }

    /** GET|POST /logout: end the session here, then at the hub (which ends it in the other apps too). */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away(hub_url('logout'));
    }
}
