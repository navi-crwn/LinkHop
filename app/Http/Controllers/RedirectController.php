<?php

namespace App\Http\Controllers;

use App\Jobs\LogLinkClick;
use App\Models\Link;
use App\Models\RetiredSlug;
use App\Services\LinkService;
use App\Services\UserAgentService;
use App\Support\LinkRouting;
use App\Support\ClickSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * hel.ink/l/{slug}: the short link itself. Bio pages live under /b/ only.
 */
class RedirectController extends Controller
{
    public function __construct(private readonly LinkService $links) {}

    public function __invoke(Request $request, string $slug)
    {
        $link = Cache::remember($this->links->cacheKey($slug), 300, function () use ($slug) {
            return Link::with(['user'])->where('slug', $slug)->first();
        });
        if ($link?->isGuestExpired() || (! $link && RetiredSlug::isBlocked($slug))) {
            return response()->view('errors.link-missing', ['slug' => $slug, 'expired' => true], 410);
        }
        // Recover a browser-cached legacy 301 only when no link owns the name.
        // The query changes the cache key, avoiding the old /name -> /l/name loop.
        if (! $link && $request->isMethodSafe() && in_array($slug, config('hub-pages'), true)) {
            return redirect()->away('https://hel.ink/'.rawurlencode($slug).'?r=l', 302)
                ->header('Cache-Control', 'no-store');
        }
        if (! $link || $link->status !== Link::STATUS_ACTIVE || $link->user?->isSuspended()) {
            return $this->notFound($slug);
        }
        // The order is fixed: not yet, expired, out of clicks. Only the last two send visitors on to a fallback address.
        if ($link->isNotStarted()) {
            return $this->ended($link, 'scheduled');
        }
        if ($link->isExpired()) {
            return $this->ended($link, 'expired');
        }
        // The cached copy may be minutes old: the click count of a limited link is always read fresh.
        if ($link->max_clicks !== null && $link->isLimitReached((int) Link::whereKey($link->id)->value('clicks'))) {
            return $this->ended($link, 'limit');
        }
        $passwordKey = 'link_password_'.$link->id;
        if ($link->requiresPassword() && session($passwordKey) !== hash('sha256', $link->password_hash)) {
            if ($request->isMethod('post')) {
                $request->validate(['password' => ['required', 'string']]);
                $key = 'link-password:'.$link->id.':'.$request->ip();
                if (RateLimiter::tooManyAttempts($key, 10)) {
                    return back()->withErrors(['password' => 'Too many attempts. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' min.']);
                }
                if (! Hash::check($request->input('password'), $link->password_hash)) {
                    RateLimiter::hit($key, 600);

                    return back()->withErrors(['password' => 'Incorrect password.']);
                }
                RateLimiter::clear($key);
                session([$passwordKey => hash('sha256', $link->password_hash)]);
            } else {
                return response()->view('link-password', compact('link'));
            }
        }
        if ($link->requiresContentWarning()) {
            $sessionKey = 'link_gate_passed_'.$link->id;
            $cookieKey = 'hop_gate_link_'.$link->slug;
            $isUnlocked = session($sessionKey) || $request->cookie($cookieKey);

            if (! $isUnlocked) {
                if ($request->isMethod('post') && in_array($request->input('gate_confirm'), ['yes', '1', 'continue'], true)) {
                    session([$sessionKey => true]);
                    $response = redirect()->to($request->fullUrl(), 303);
                    if ($request->boolean('remember')) {
                        $response->withCookie(new Cookie($cookieKey, '1', now()->addDay(), '/', null, $request->isSecure(), true, false, 'lax'));
                    }

                    return $response;
                }

                return response()->view('link-gate', compact('link'));
            }
        }
        $counted = false;
        if ($link->max_clicks !== null && ! app(UserAgentService::class)->parse($request->userAgent())['bot']) {
            // Take the click before sending the visitor on: when the last one is gone, the next visitor is turned away,
            // however many arrive at once. The log job then records the visit without counting it a second time.
            if (! Link::whereKey($link->id)->whereColumn('clicks', '<', 'max_clicks')->increment('clicks')) {
                return $this->ended($link, 'limit');
            }
            $counted = true;
        }
        // Routing rules and the A/B split may pick another address; crawlers and plain links keep the link's own.
        $route = LinkRouting::resolve($link, $request);
        $this->logClick($request, $link, $counted, $route['variant']);
        if ($link->enable_preview && $request->query('skip_preview') !== '1') {
            return response()->view('preview', ['targetUrl' => $route['url']]);
        }
        if ($this->shouldServePreview($request, $link)) {
            return response()
                ->view('link-preview', compact('link'))
                ->header('Refresh', '0;url='.$link->target_url);
        }

        return redirect()->away($route['url'], (int) ($link->redirect_type ?? 302));
    }

    protected function logClick(Request $request, Link $link, bool $counted = false, ?int $variant = null): void
    {
        LogLinkClick::dispatch(
            $link->id,
            $request->ip(),
            $request->headers->get('referer'),
            $request->userAgent(),
            $counted,
            $variant,
            ClickSource::fromRequest($request)
        );
    }

    /**
     * A link of an account that is not live: send the visitor to the owner's fallback address when there is one
     * (expired or out of clicks), otherwise say plainly why. Never cached.
     */
    protected function ended(Link $link, string $reason)
    {
        $fallback = $reason === 'scheduled' ? null : $link->fallback_url;
        if ($fallback && preg_match('#^https?://#i', $fallback)) {
            return redirect()->away($fallback, 302)->header('Cache-Control', 'no-store');
        }

        return response()
            ->view('errors.link-ended', ['link' => $link, 'reason' => $reason], $reason === 'scheduled' ? 404 : 410)
            ->header('Cache-Control', 'no-store');
    }

    protected function shouldServePreview(Request $request, Link $link): bool
    {
        if (! $link->custom_title && ! $link->custom_description && ! $link->custom_image_url) {
            return false;
        }
        $ua = strtolower($request->userAgent() ?? '');
        $bots = ['facebookexternalhit', 'twitterbot', 'slackbot', 'linkedinbot', 'discordbot', 'whatsapp', 'telegrambot', 'skypepreview', 'pinterest'];
        foreach ($bots as $bot) {
            if (str_contains($ua, $bot)) {
                return true;
            }
        }

        return false;
    }

    protected function notFound(?string $slug = null)
    {
        return response()
            ->view('errors.link-missing', ['slug' => $slug], 404);
    }
}
