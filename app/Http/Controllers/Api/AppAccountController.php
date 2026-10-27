<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\Folder;
use App\Models\HopApp;
use App\Models\IpBan;
use App\Models\IpWatchlist;
use App\Models\LoginHistory;
use App\Models\User;
use App\Support\HopSignature;
use App\Support\Username;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;

use function Illuminate\Support\defer;

/**
 * Lets a HOP app's own sign-in and sign-up forms use HOP ID accounts, so an
 * account made in either place works everywhere without visiting HOP ID.
 *
 * Server to server only. New apps call /api/apps/{app}/login|register signed
 * with the HOP App Protocol headers (HopSignature). PixelHop 3.x still calls
 * /api/pixelhop/login|register with its older signature
 * (X-PixelHop-Timestamp + X-PixelHop-Signature =
 * base64url(HMAC-SHA256(secret, "pixelhop-api|{timestamp}|{body}"))); both
 * use the app's secret from the registry. Turnstile runs in the app. The app
 * sends the visitor's IP in the signed body, so this side applies the same IP
 * bans as the web sign-in and limits failed attempts per email and per
 * visitor IP (not per app server, which every visitor shares).
 *
 * Accounts with two-factor codes, and accounts whose email is not confirmed
 * yet, are not signed in here: the app sends those people through the
 * normal /sso/{app} flow instead (AppSsoController), like the web.
 */
class AppAccountController extends Controller
{
    private const MAX_AGE = 120;

    public function login(Request $request, string $app = 'pixelhop'): JsonResponse
    {
        if ($denied = $this->verifySignature($request, $app) ?? $this->bannedIp($request)) {
            return $denied;
        }
        $hopApp = $request->attributes->get('hop_app');
        $email = strtolower(trim((string) $request->json('email', '')));
        $password = (string) $request->json('password', '');
        $ip = $this->visitorIp($request);
        $emailKey = 'app-login:'.sha1($email);
        $ipKey = 'app-login-ip:'.$ip;
        if (RateLimiter::tooManyAttempts($emailKey, 10) || ($ip !== '' && RateLimiter::tooManyAttempts($ipKey, 30))) {
            return response()->json(['error' => 'too_many_attempts'], 429);
        }
        $user = User::where('email', $email)->first();
        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($emailKey, 15 * 60);
            if ($ip !== '') {
                RateLimiter::hit($ipKey, 15 * 60);
            }

            return response()->json(['error' => 'bad_credentials'], 401);
        }
        RateLimiter::clear($emailKey);
        if ($user->isSuspended()) {
            if ($ip !== '') {
                IpWatchlist::addOrUpdate($ip, $user->id, $hopApp->name.' login attempt by suspended user: '.$user->email);
            }

            return response()->json(['error' => 'suspended'], 403);
        }
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            return response()->json(['error' => 'two_factor'], 409);
        }
        // Same rule as the /sso/pixelhop ticket: confirm the address first.
        if (! $user->hasVerifiedEmail() && ! $user->google_id) {
            return response()->json(['error' => 'unverified'], 403);
        }
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();
        LoginHistory::create(['user_id' => $user->id, 'ip_address' => $ip ?: null, 'browser' => $hopApp->name, 'logged_in_at' => now()]);
        LoginHistory::keepLatest($user->id);

        return response()->json(['user' => $this->payload($user, $hopApp)]);
    }

    public function register(Request $request, string $app = 'pixelhop'): JsonResponse
    {
        if ($denied = $this->verifySignature($request, $app) ?? $this->bannedIp($request)) {
            return $denied;
        }
        $hopApp = $request->attributes->get('hop_app');
        $data = [
            'name' => trim((string) $request->json('name', '')),
            'email' => strtolower(trim((string) $request->json('email', ''))),
            'password' => (string) $request->json('password', ''),
            'tos_accepted' => (bool) $request->json('tos_accepted', false),
        ];
        $v = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'indisposable', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
            'tos_accepted' => ['accepted'],
        ], [
            'email.indisposable' => 'Disposable email addresses are not allowed.',
            'email.unique' => 'An account with this email already exists. Sign in instead.',
            'tos_accepted.accepted' => 'Please accept the terms.',
        ]);
        if ($v->fails()) {
            return response()->json(['error' => 'invalid', 'message' => $v->errors()->first()], 422);
        }
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_USER,
        ]);
        $user->forceFill(['username' => Username::suggest($user->name, $user)])->saveQuietly();
        Folder::create(['name' => 'Default', 'user_id' => $user->id, 'is_default' => true]);
        // SMTP can take seconds per message: send both after the response.
        defer(function () use ($user) {
            event(new Registered($user)); // sends the "confirm your email" message
            try {
                Mail::to($user->email)->send(new WelcomeEmail($user, false));
            } catch (\Exception $e) {
                Log::error('Failed to send welcome email', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            }
        });

        return response()->json(['user' => $this->payload($user, $hopApp)], 201);
    }

    /** The same facts the SSO ticket carries (v2 profile), plus `admin` for this app. */
    private function payload(User $user, HopApp $app): array
    {
        return $user->hopProfile() + ['admin' => $user->isAdminOf($app->slug)];
    }

    /** The visitor's IP as PixelHop saw it (signed with the body), or ''. */
    private function visitorIp(Request $request): string
    {
        $ip = (string) $request->json('ip', '');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /** The web's IP bans (CheckIpBan) apply to the visitor behind PixelHop too. */
    private function bannedIp(Request $request): ?JsonResponse
    {
        $ip = $this->visitorIp($request);
        $ban = $ip === '' ? null : IpBan::where('ip_address', $ip)->first();
        if ($ban && ! $ban->isExpired()) {
            return response()->json(['error' => 'banned'], 403);
        }

        return null;
    }

    /**
     * New apps: HOP App Protocol headers (VerifyHopSignature already ran on
     * /api/apps/{app}/…). PixelHop 3.x: the older per-app signature. Either
     * way the app ends up in the request attributes.
     */
    private function verifySignature(Request $request, string $slug): ?JsonResponse
    {
        if ($hopApp = $request->attributes->get('hop_app')) {
            if ($hopApp->slug !== $slug || ! $hopApp->can('account_api')) {
                return response()->json(['error' => 'not_configured'], 404);
            }

            return null;
        }
        $hopApp = HopApp::find_($slug);
        if (! $hopApp || ! $hopApp->isConfigured() || ! $hopApp->can('account_api')) {
            return response()->json(['error' => 'not_configured'], 404);
        }
        $ts = (string) $request->header('X-PixelHop-Timestamp', '');
        $sig = (string) $request->header('X-PixelHop-Signature', '');
        if (! ctype_digit($ts) || abs(time() - (int) $ts) > self::MAX_AGE) {
            return response()->json(['error' => 'stale_request'], 401);
        }
        foreach ($hopApp->secrets() as $secret) {
            $expected = HopSignature::b64(hash_hmac('sha256', 'pixelhop-api|'.$ts.'|'.$request->getContent(), $secret, true));
            if (hash_equals($expected, $sig)) {
                $request->attributes->set('hop_app', $hopApp);

                return null;
            }
        }

        return response()->json(['error' => 'bad_signature'], 401);
    }
}
