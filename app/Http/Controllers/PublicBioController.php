<?php

namespace App\Http\Controllers;

use App\Models\BioLink;
use App\Models\BioPage;
use App\Services\GeoIpService;
use App\Services\UserAgentService;
use App\Support\QrPng;
use App\Support\SafeContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

use function Illuminate\Support\defer;

class PublicBioController extends Controller
{
    private const VISITOR_COOKIE = 'hel_vid';

    private const COUNTED_CLICKS_PER_IP = 20;

    public function show(Request $request, string $slug)
    {
        $bioPage = BioPage::with(['links' => function ($query) {
            $query->active()->ordered();
        }])
            ->where('slug', $slug)
            ->first();
        // Page not found or not published
        if (! $bioPage) {
            return response()->view('bio.not-found', ['slug' => $slug], 404);
        }
        if (! $this->isVisible($bioPage)) {
            return response()->view('bio.not-found', ['slug' => $slug, 'unpublished' => true], 404);
        }
        // Password protection check
        if ($this->needsPassword($bioPage)) {
            $sessionKey = "bio_unlocked_{$bioPage->id}";
            if ($request->isMethod('post') && $request->has('password')) {
                $key = 'bio-password:'.$bioPage->id.':'.$request->ip();
                if (RateLimiter::tooManyAttempts($key, 10)) {
                    return view('bio.password', compact('bioPage'))->withErrors(['password' => 'Too many attempts. Try again later.']);
                }
                RateLimiter::hit($key, 600);
                if ($this->passwordMatches($request->input('password'), $bioPage->password)) {
                    RateLimiter::clear($key);
                    session([$sessionKey => true]);

                    // Use 303 redirect (See Other) to convert POST to GET - prevents browser resubmit warning
                    return redirect()->to(url()->current(), 303);
                } else {
                    return view('bio.password', compact('bioPage'))->withErrors(['password' => 'Incorrect password']);
                }
            } else {
                return view('bio.password', compact('bioPage'));
            }
        }
        // 18+ Age verification check
        if ($this->needsAgeCheck($bioPage)) {
            $sessionKey = "bio_age_verified_{$bioPage->id}";
            if ($request->isMethod('post') && $request->input('age_confirm') === 'yes') {
                session([$sessionKey => true]);

                // Use 303 redirect (See Other) to convert POST to GET - prevents browser resubmit warning
                return redirect()->to(url()->current(), 303);
            } else {
                return view('bio.age-verify', compact('bioPage'));
            }
        }
        $this->trackView($bioPage);

        return view('bio.show', compact('bioPage'));
    }

    public function click(BioLink $bioLink)
    {
        // Disabled blocks / hidden pages must not keep redirecting.
        $bioPage = $bioLink->bioPage;
        $target = SafeContent::url($bioLink->url);
        if (! $bioLink->is_active || ! $this->isVisible($bioPage) || $target === '#') {
            abort(404);
        }
        // Same gates as show(): never reveal a destination behind a password or age check.
        if ($this->needsPassword($bioPage) || $this->needsAgeCheck($bioPage)) {
            return redirect()->route('bio.public.show', $bioPage->slug);
        }
        $ipAddress = request()->ip();
        $ua = app(UserAgentService::class)->parse(request()->userAgent());
        $visitorId = $this->visitorId();
        $newVisitorId = $visitorId === null && ! $ua['bot'] ? Str::random(32) : null;
        // Visitors always reach the destination; limits only decide whether the click is counted.
        // Dedup by visitor cookie (people sharing one IP still count separately), falling back to IP + User-Agent.
        // At most 20 counted clicks per IP per block per 10 minutes, so clearing cookies cannot inflate counts.
        $dedupKey = 'bio_click:'.$bioLink->id.':'.($visitorId ?? sha1($ipAddress.'|'.request()->userAgent()));
        $ipKey = "bio_click_ip:{$bioLink->id}:{$ipAddress}";
        if (! $ua['bot']
            && ! RateLimiter::tooManyAttempts($ipKey, self::COUNTED_CLICKS_PER_IP)
            && Cache::add($dedupKey, true, now()->addMinutes(10))) {
            RateLimiter::hit($ipKey, 600);
            $click = [
                'ip_address' => $ipAddress,
                'device' => strtolower($ua['device']),
                'browser' => $ua['browser'],
                'referrer' => request()->header('referer'),
            ];
            // The country lookup can take seconds for a new IP; record after the redirect is sent.
            defer(fn () => $bioLink->recordClick($click + ['country' => $this->getCountryFromIp($ipAddress)]));
        }
        $response = redirect()->away($target)->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        if ($newVisitorId !== null) {
            // The click just made under IP + User-Agent also covers the new cookie, so it is not counted twice.
            Cache::put("bio_click:{$bioLink->id}:{$newVisitorId}", true, now()->addMinutes(10));
            $response->withCookie(cookie(self::VISITOR_COOKIE, $newVisitorId, 60 * 24 * 365, null, null, null, true, false, 'lax'));
        }

        return $response;
    }

    /**
     * Anonymous random id used only to de-duplicate clicks; null when the visitor has none yet.
     */
    protected function visitorId(): ?string
    {
        $id = request()->cookie(self::VISITOR_COOKIE);

        return is_string($id) && preg_match('/^[A-Za-z0-9]{32}$/', $id) ? $id : null;
    }

    public function qr(string $slug)
    {
        $bioPage = BioPage::where('slug', $slug)->first();
        abort_unless($this->isVisible($bioPage), 404);
        $url = url('/b/'.$bioPage->slug);
        $qrCode = QrPng::render($url, 512, margin: 2);

        return response($qrCode)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', "attachment; filename=\"{$bioPage->slug}-qr.png\"");
    }

    protected function isVisible(?BioPage $bioPage): bool
    {
        return $bioPage && $bioPage->is_published && ! $bioPage->user?->isSuspended();
    }

    protected function needsPassword(BioPage $bioPage): bool
    {
        return $bioPage->password_enabled && $bioPage->password && ! session("bio_unlocked_{$bioPage->id}");
    }

    protected function needsAgeCheck(BioPage $bioPage): bool
    {
        return $bioPage->is_adult_content && ! session("bio_age_verified_{$bioPage->id}");
    }

    protected function trackView(BioPage $bioPage): void
    {
        $ipAddress = request()->ip();
        $cacheKey = "bio_view:{$bioPage->id}:{$ipAddress}";
        if (! Cache::has($cacheKey)) {
            $bioPage->incrementViews();
            Cache::put($cacheKey, true, now()->addDay());
        }
    }

    /**
     * Verify a submitted bio-page password against the stored value.
     *
     * Supports hashed passwords (Hash::check) and falls back to a
     * constant-time comparison for legacy plaintext rows.
     */
    protected function passwordMatches(?string $input, ?string $stored): bool
    {
        if ($input === null || $stored === null || $stored === '') {
            return false;
        }
        if (Str::startsWith($stored, ['$2y$', '$2a$', '$2b$', '$argon2i$', '$argon2id$'])) {
            return Hash::check($input, $stored);
        }

        return hash_equals($stored, $input);
    }

    protected function getCountryFromIp(string $ipAddress): ?string
    {
        try {
            return app(GeoIpService::class)->country($ipAddress);
        } catch (\Exception $e) {
            return null;
        }
    }
}
