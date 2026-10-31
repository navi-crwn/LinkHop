<?php

namespace App\Http\Controllers;

use App\Jobs\LogLinkClick;
use App\Models\Link;
use App\Models\RetiredSlug;
use App\Services\LinkService;
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
        if (! $link || $link->status !== Link::STATUS_ACTIVE || $link->isExpired() || $link->user?->isSuspended()) {
            return $this->notFound($slug);
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
        $this->logClick($request, $link);
        if ($link->enable_preview && $request->query('skip_preview') !== '1') {
            return response()->view('preview', ['targetUrl' => $link->target_url]);
        }
        if ($this->shouldServePreview($request, $link)) {
            return response()
                ->view('link-preview', compact('link'))
                ->header('Refresh', '0;url='.$link->target_url);
        }

        return redirect()->away($link->target_url, (int) ($link->redirect_type ?? 302));
    }

    protected function logClick(Request $request, Link $link): void
    {
        LogLinkClick::dispatch(
            $link->id,
            $request->ip(),
            $request->headers->get('referer'),
            $request->userAgent()
        );
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
