<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\IpWatchlist;
use App\Models\User;
use App\Services\LoginRecorder;
use App\Services\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly TurnstileService $turnstile,
        private readonly LoginRecorder $recorder,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! $this->turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()
                ->withErrors(['turnstile' => 'Security verification failed, please try again.'])
                ->onlyInput('email');
        }
        $user = User::where('email', $request->email)->first();
        if ($user && $user->status === User::STATUS_SUSPENDED) {
            IpWatchlist::addOrUpdate(
                $request->ip(),
                $user->id,
                'Login attempt by suspended user: '.$user->email
            );

            return redirect()->route('suspended')
                ->with('email', $request->email);
        }
        $request->authenticate();
        $request->session()->regenerate();
        $user = $request->user();
        if ($user) {
            $this->recorder->record($user, $request->ip(), $request->userAgent(), 'password');
            if (! $user->two_factor_secret || ! $user->two_factor_confirmed_at) {
                $request->session()->put('2fa_verified', true);
            }
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->forget('2fa_verified');

        return redirect('/');
    }
}
