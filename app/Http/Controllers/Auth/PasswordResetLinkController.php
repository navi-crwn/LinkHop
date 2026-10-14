<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'catchphrase' => ['required', 'string'],
        ]);
        // Catchphrases are low-entropy (3 words from a short list): throttle hard
        // per account and per IP, and never allow them for privileged accounts.
        $keys = ['catchphrase:email:'.strtolower($request->email), 'catchphrase:ip:'.$request->ip()];
        foreach ([[$keys[0], 5], [$keys[1], 20]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return back()->withErrors(['catchphrase' => 'Too many attempts. Use the email reset link instead.'])->withInput($request->only('email'));
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, 3600);
        }
        $user = User::where('email', $request->email)->first();
        $matches = $user && is_string($user->catchphrase)
            && hash_equals(strtolower(trim($user->catchphrase)), strtolower(trim($request->catchphrase)));
        if (! $matches) {
            return back()->withErrors(['catchphrase' => 'The provided email or catchphrase is incorrect.'])->withInput($request->only('email'));
        }
        if ($user->isSuperAdmin() || $user->two_factor_confirmed_at) {
            return back()->withErrors(['catchphrase' => 'This account must be reset with the email link.'])->withInput($request->only('email'));
        }
        RateLimiter::clear($keys[0]);
        $request->session()->regenerate();
        session(['password_reset_email' => $user->email, 'password_reset_at' => now()->timestamp]);

        return redirect()->route('password.reset.form');
    }

    public function storeEmailOnly(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);
        // Send password reset link via Laravel's built-in password reset
        Password::sendResetLink(
            $request->only('email')
        );

        // Same answer whether or not the account exists (no email enumeration).
        return back()->with(['status' => __(Password::RESET_LINK_SENT)]);
    }

    public function showResetForm(): View
    {
        if (! session('password_reset_email')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password-form');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $email = session('password_reset_email');
        // The verified catchphrase step is only valid for 10 minutes.
        if (! $email || now()->timestamp - (int) session('password_reset_at') > 600) {
            session()->forget(['password_reset_email', 'password_reset_at']);

            return redirect()->route('password.request')->withErrors(['email' => 'Session expired. Please try again.']);
        }
        $user = User::where('email', $email)->first();
        if (! $user) {
            return redirect()->route('password.request')->withErrors(['email' => 'User not found.']);
        }
        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60), // invalidate "remember me" cookies
        ])->save();
        session()->forget(['password_reset_email', 'password_reset_at']);

        return redirect()->route('login')->with('status', 'Your password has been reset successfully. You can now login with your new password.');
    }
}
