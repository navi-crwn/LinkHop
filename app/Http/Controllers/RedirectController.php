<?php

namespace App\Http\Controllers;

use App\Jobs\LogLinkClick;
use App\Models\Link;
use App\Models\RetiredSlug;
use App\Services\LinkService;
use App\Services\UserAgentService;
use App\Support\ClickSource;
use App\Support\DeepLinks;
use App\Support\EncryptedNoteLink;
use App\Support\LinkRouting;
use App\Support\Webhooks;
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
        $link = Cache::get($this->links->cacheKey($slug));
        if (! $link) {
            $link = Link::with(['user'])->where('slug', $slug)->first();
            // A row saved before encrypted note links were refused still redirects, but no copy of its key is made.
            if (! $link || ! app(EncryptedNoteLink::class)->holdsKey($link->getAttributes())) {
                Cache::put($this->links->cacheKey($slug), $link, 300);
            }
        }
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
                    $response = $this->continueTo($request, $link);
                    if ($request->boolean('remember')) {
                        $response->withCookie(new Cookie($cookieKey, '1', now()->addDay(), '/', null, $request->isSecure(), true, false, 'lax'));
                    }

                    return $response;
                }

                return response()->view('link-gate', compact('link'));
            }
        }
        // A guest link to a site the allowlist does not know, not yet cleared by abuse:scan: say so before sending anyone on.
        if ($link->isPendingSafety()) {
            $sessionKey = 'link_unverified_passed_'.$link->id;
            if (! session($sessionKey)) {
                if ($request->isMethod('post') && $request->input('unverified_confirm') === 'yes') {
                    session([$sessionKey => true]);

                    return $this->continueTo($request, $link);
                }

                return response()->view('link-unverified', ['link' => $link, 'host' => $this->destinationHost($link)])
                    ->header('Cache-Control', 'no-store')
                    ->header('X-Robots-Tag', 'noindex');
            }
        }
        // Every gate is passed. A form post (the password) is never the visit itself: open the link with a fresh GET,
        // which is the request that counts the click and leaves for the destination.
        if ($request->isMethod('post')) {
            return $this->continueTo($request, $link);
        }
        $counted = false;
        if ($link->max_clicks !== null && ! app(UserAgentService::class)->parse($request->userAgent())['bot']) {
            // Take the click before sending the visitor on: when the last one is gone, the next visitor is turned away,
            // however many arrive at once. The log job then records the visit without counting it a second time.
            if (! Link::whereKey($link->id)->whereColumn('clicks', '<', 'max_clicks')->increment('clicks')) {
                return $this->ended($link, 'limit');
            }
            $counted = true;
            Webhooks::clickCounted($link);
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

        // "Open in the app": on iOS and Android the app of the destination is tried first (a page that does it and falls back).
        if ($link->app_links && ($deep = DeepLinks::resolve($link, $route['url'], app(UserAgentService::class)->parse($request->userAgent()), $route['url'] !== $link->target_url))) {
            return response()
                ->view('link-app-open', ['link' => $link, 'deep' => $deep, 'web' => $route['url']])
                ->header('Cache-Control', 'no-store')
                ->header('X-Robots-Tag', 'noindex');
        }

        return $this->away($link, $route['url']);
    }

    /**
     * Send the visitor to the destination with the link's own redirect type, except when the link can still
     * change what that visitor meets (see Link::hasDynamicFeatures()). Browsers keep a 301 or a 308 and answer
     * from their own store, so a warning, password, rule, limit or expiry added after someone's first visit
     * would never reach them. Such links answer 302 and are not cached; a plain link keeps its permanent type.
     */
    protected function away(Link $link, string $url)
    {
        $code = (int) ($link->redirect_type ?? 302);
        $dynamic = $link->hasDynamicFeatures();
        if ($dynamic && in_array($code, [301, 308], true)) {
            $code = 302;
        }
        $response = redirect()->away($url, $code);

        return $dynamic ? $response->header('Cache-Control', 'no-store') : $response;
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

    /**
     * The answer to an accepted gate form (password, content warning, unverified destination): a page that opens the link
     * again with a GET. The browser checks CSP form-action against every redirect that follows a form post, so a 303 back
     * to /l/{slug} that then leaves for another site was blocked and the visitor stayed on the gate. A Refresh is a new
     * navigation, not part of the form post; the button covers a browser that ignores it.
     */
    protected function continueTo(Request $request, Link $link)
    {
        $url = $request->fullUrl();

        return response()
            ->view('link-continue', ['link' => $link, 'url' => $url, 'host' => $this->destinationHost($link)])
            ->header('Refresh', '0;url='.$url)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    /** The destination's host as a person would read it (no www., no brackets), for the warning page. */
    protected function destinationHost(Link $link): string
    {
        $host = (string) parse_url($link->target_url, PHP_URL_HOST);

        return (string) preg_replace('/^www\./', '', strtolower(trim($host, '[]')));
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
