<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, TurnstileService $turnstile): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);
        if (! $turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()->withErrors(['turnstile' => 'Security verification failed, please try again.'])->onlyInput('email');
        }
        Password::sendResetLink($request->only('email'));

        // Same answer whether or not the account exists (no email enumeration).
        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
