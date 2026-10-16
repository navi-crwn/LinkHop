<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;

/**
 * Lets PixelHop's own sign-in and sign-up forms use HEL.ink accounts, so an
 * account made in either app works in both without visiting the other.
 *
 * Server to server only: every call is signed by PixelHop with the shared
 * SSO secret (X-PixelHop-Timestamp + X-PixelHop-Signature =
 * base64url(HMAC-SHA256(secret, "pixelhop-api|{timestamp}|{body}"))) and
 * must be under two minutes old. Turnstile and per-IP limits run on
 * PixelHop; this side also limits attempts per email address.
 *
 * Accounts with two-factor codes are not signed in here: PixelHop sends
 * those people through the normal /sso/pixelhop flow instead.
 */
class PixelHopAccountController extends Controller
{
    private const MAX_AGE = 120;

    public function login(Request $request): JsonResponse
    {
        if ($denied = $this->verifySignature($request)) {
            return $denied;
        }
        $email = strtolower(trim((string) $request->json('email', '')));
        $password = (string) $request->json('password', '');
        $key = 'pixelhop-login:'.sha1($email);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['error' => 'too_many_attempts'], 429);
        }
        $user = User::where('email', $email)->first();
        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 15 * 60);

            return response()->json(['error' => 'bad_credentials'], 401);
        }
        RateLimiter::clear($key);
        if ($user->isSuspended()) {
            return response()->json(['error' => 'suspended'], 403);
        }
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            return response()->json(['error' => 'two_factor'], 409);
        }
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => (string) $request->json('ip', '')])->saveQuietly();

        return response()->json(['user' => $this->payload($user)]);
    }

    public function register(Request $request): JsonResponse
    {
        if ($denied = $this->verifySignature($request)) {
            return $denied;
        }
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
        Folder::create(['name' => 'Default', 'user_id' => $user->id, 'is_default' => true]);
        event(new Registered($user)); // sends the "confirm your email" message
        try {
            Mail::to($user->email)->send(new WelcomeEmail($user, false));
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }

        return response()->json(['user' => $this->payload($user)], 201);
    }

    /** The same facts the SSO ticket carries, plus whether the email is confirmed. */
    private function payload(User $user): array
    {
        return [
            'sub' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'avatar' => $user->avatar ?: '',
            'admin' => $user->isSuperAdmin(),
            'verified' => $user->hasVerifiedEmail() || (bool) $user->google_id,
        ];
    }

    private function verifySignature(Request $request): ?JsonResponse
    {
        $secret = (string) config('services.pixelhop.sso_secret');
        if (strlen($secret) < 32) {
            return response()->json(['error' => 'not_configured'], 404);
        }
        $ts = (string) $request->header('X-PixelHop-Timestamp', '');
        $sig = (string) $request->header('X-PixelHop-Signature', '');
        if (! ctype_digit($ts) || abs(time() - (int) $ts) > self::MAX_AGE) {
            return response()->json(['error' => 'stale_request'], 401);
        }
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', 'pixelhop-api|'.$ts.'|'.$request->getContent(), $secret, true)), '+/', '-_'), '=');
        if (! hash_equals($expected, $sig)) {
            return response()->json(['error' => 'bad_signature'], 401);
        }

        return null;
    }
}
