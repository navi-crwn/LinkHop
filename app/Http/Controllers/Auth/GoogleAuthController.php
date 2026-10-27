<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\Folder;
use App\Models\User;
use App\Services\LoginRecorder;
use App\Services\NotificationService;
use App\Support\Username;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function redirectToGoogle()
    {
        if (request()->get('from_app') == '1') {
            // From an app's "Continue with Google": sign in or sign up, then
            // back to the app (the intended URL is its ticket step).
            session(['google_oauth_from' => 'app']);
        } elseif (request()->get('from_register') == '1') {
            session(['google_oauth_from' => 'register']);
        } else {
            session(['google_oauth_from' => 'login']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $fromPage = session('google_oauth_from', 'login');
            session()->forget('google_oauth_from');
            // Accounts are matched by email, so only trust addresses Google has verified.
            $raw = (array) ($googleUser->user ?? []);
            if (! ($raw['email_verified'] ?? $raw['verified_email'] ?? false)) {
                return redirect()->route('login')->with('error', 'Your Google email address is not verified.');
            }
            $user = User::where('email', $googleUser->email)
                ->orWhere('google_id', $googleUser->id)
                ->first();
            if ($fromPage === 'login' && ! $user) {
                return redirect()->route('login')
                    ->with('error', 'Account not found. Please register first to continue.')
                    ->with('google_email', $googleUser->email);
            }
            if ($fromPage === 'register' && $user) {
                return redirect()->route('login')->with('error', 'Account already exists. Please login instead.');
            }
            if ($user) {
                if (! $user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar,
                    ]);
                }
                // Google vouches for this address (checked above), and it is the
                // account's email: accounts made before the fix below get verified here.
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
                if ($user->isSuspended()) {
                    return redirect()->route('suspended')->with('error', 'Your account has been suspended.');
                }
                Auth::login($user, true);
                app(LoginRecorder::class)->record($user, request()->ip(), request()->userAgent(), 'google');

                return redirect()->intended('dashboard')->with('success', 'Welcome back, '.$user->name.'!');
            } else {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    'password' => Hash::make('TEMPORARY_GOOGLE_OAUTH_NO_PASSWORD_'.Str::random(32)), // Temporary password
                ]);
                // Google already verified the address. email_verified_at is not
                // mass assignable, so create() silently dropped it before.
                $user->forceFill(['email_verified_at' => now(), 'username' => Username::suggest($user->name ?: $user->email, $user)])->save();
                try {
                    $this->notificationService->notifyNewUser([
                        'name' => $user->name,
                        'email' => $user->email,
                        'registration_method' => 'Google OAuth',
                        'registered_at' => $user->created_at->format('Y-m-d H:i:s'),
                    ]);
                } catch (Exception $e) {
                    \Log::error('Failed to send new user notification', [
                        'error' => $e->getMessage(),
                        'user_id' => $user->id,
                    ]);
                }
                try {
                    \Mail::to($user->email)->send(new WelcomeEmail($user, true));
                } catch (Exception $e) {
                    \Log::error('Failed to send welcome email', [
                        'error' => $e->getMessage(),
                        'user_id' => $user->id,
                    ]);
                }
                Folder::create([
                    'name' => 'Default',
                    'user_id' => $user->id,
                    'is_default' => true,
                ]);
                Auth::login($user, true);

                if ($fromPage === 'app') {
                    return redirect()->intended(route('onboarding'));
                }

                return redirect()->route('onboarding')->with('success', 'Welcome to '.config('app.name').', '.$user->name.'!');
            }
        } catch (Exception $e) {
            \Log::error('Google OAuth Error: '.$e->getMessage());

            return redirect()->route('login')
                ->with('error', 'Unable to login with Google. You may not have registered yet. Please register first.');
        }
    }
}
