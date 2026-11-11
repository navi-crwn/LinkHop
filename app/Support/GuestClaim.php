<?php

namespace App\Support;

use App\Models\AbuseFlag;
use App\Models\Link;
use App\Models\User;
use App\Services\LinkService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Claiming the short links a guest made before signing in.
 *
 * The browser that made a guest link receives a random claim token in a cookie (HttpOnly, Secure, SameSite=Lax, 30
 * days). Only a keyed hash of the token is stored, in links.claim_token_hash. The token is never put in a URL, a log
 * line or a response body. After signing in, the same browser can move those links to the account, once: the move is
 * a single UPDATE limited to links that are still unowned, active, not flagged, not reported and not expired, so a
 * stolen link id without the cookie claims nothing and a second claim finds nothing left.
 * The per-address guest limits (GuestLinkPolicy, creator_hash) are not touched.
 */
class GuestClaim
{
    public const COOKIE = 'hop_claim';

    public const DAYS = 30;

    /** A new random token (the cookie value). */
    public static function newToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    /** The keyed hash stored with a link. */
    public static function hash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    /** A token as the cookie holds it, or null when it is absent or not shaped like one. */
    public static function token(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && preg_match('/^[a-f0-9]{48}$/', $token) ? $token : null;
    }

    /** The cookie to send: the existing token, or a new one. Returns [token, cookie]. */
    public static function issue(Request $request): array
    {
        $token = self::token($request) ?? self::newToken();

        return [$token, self::cookie($token)];
    }

    /**
     * Secure follows the session configuration (SESSION_SECURE_COOKIE, which defaults to "APP_URL is https"), so the
     * flow can be tried on local http by setting it to false. Only an explicit false turns it off: unset stays Secure.
     */
    public static function secure(): bool
    {
        return config('session.secure') !== false;
    }

    public static function cookie(string $token): Cookie
    {
        return Cookie::create(self::COOKIE, $token, now()->addDays(self::DAYS), '/', null, self::secure(), true, false, Cookie::SAMESITE_LAX);
    }

    /** A cookie that clears the token. */
    public static function forgetCookie(): Cookie
    {
        return Cookie::create(self::COOKIE, '', 1, '/', null, self::secure(), true, false, Cookie::SAMESITE_LAX);
    }

    /** Links this token could claim right now. Never queried when the browser has no token. */
    public static function claimable(?string $token): Builder
    {
        $query = Link::query();
        if ($token === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereNull('user_id')
            ->where('claim_token_hash', self::hash($token))
            ->where('status', Link::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('safety_status')->orWhere('safety_status', '!=', Link::SAFETY_FLAGGED))
            ->whereNot(fn ($q) => $q->guestExpired())
            ->whereNotExists(fn ($q) => $q->from('abuse_reports')->whereColumn('abuse_reports.slug', 'links.slug')->where('abuse_reports.status', '!=', 'dismissed'))
            ->whereNotExists(fn ($q) => $q->from('abuse_flags')->where('abuse_flags.subject_type', 'link')->whereColumn('abuse_flags.subject_id', 'links.id')
                ->where('abuse_flags.action', AbuseFlag::ACTION_DISABLED)->where('abuse_flags.status', '!=', AbuseFlag::STATUS_DISMISSED));
    }

    public static function pending(Request $request): int
    {
        $token = self::token($request);

        return $token === null ? 0 : self::claimable($token)->count();
    }

    /**
     * Move the claimable links to the user, oldest first, as many as fit under the account's active-link cap
     * (config limits.user.max_active_links; the daily creation allowance is about creating, not owning, so it does not
     * apply). The user's row is locked while the room is counted and the links move, like QuotaService::createWithinLimits,
     * so two claims cannot both take the last slot. Claimed links are active, so each one uses a slot.
     *
     * @return array{moved: int, left: int, max: int} left = claimable links that did not fit
     */
    public static function claim(string $token, int $userId): array
    {
        $max = (int) config('limits.user.max_active_links');
        $slugs = [];
        $moved = DB::transaction(function () use ($token, $userId, $max, &$slugs) {
            User::whereKey($userId)->lockForUpdate()->value('id');
            $room = max(0, $max - Link::where('user_id', $userId)->where('status', Link::STATUS_ACTIVE)->count());
            $ids = self::claimable($token)->orderBy('id')->limit($room)->pluck('id');
            if ($ids->isEmpty()) {
                return 0;
            }
            $slugs = Link::whereIn('id', $ids)->pluck('slug')->all();

            // The same conditions again in the UPDATE itself: two requests cannot both move a link.
            return self::claimable($token)->whereIn('id', $ids)->update(['user_id' => $userId, 'claim_token_hash' => null]);
        });
        foreach ($slugs as $slug) {
            app(LinkService::class)->forgetCacheBySlug($slug);
        }

        return ['moved' => $moved, 'left' => self::claimable($token)->count(), 'max' => $max];
    }
}
