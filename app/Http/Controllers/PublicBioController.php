<?php

namespace App\Http\Controllers;

use App\Models\BioLink;
use App\Models\BioPage;
use App\Services\GeoIpService;
use App\Services\UserAgentService;
use App\Support\SafeContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PublicBioController extends Controller
{
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
        if (! $bioPage->is_published || $bioPage->user?->isSuspended()) {
            return response()->view('bio.not-found', ['slug' => $slug, 'unpublished' => true], 404);
        }
        // Password protection check
        if ($bioPage->password_enabled && $bioPage->password) {
            $sessionKey = "bio_unlocked_{$bioPage->id}";
            if (! session($sessionKey)) {
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
        }
        // 18+ Age verification check
        if ($bioPage->is_adult_content) {
            $sessionKey = "bio_age_verified_{$bioPage->id}";
            if (! session($sessionKey)) {
                if ($request->isMethod('post') && $request->input('age_confirm') === 'yes') {
                    session([$sessionKey => true]);

                    // Use 303 redirect (See Other) to convert POST to GET - prevents browser resubmit warning
                    return redirect()->to(url()->current(), 303);
                } else {
                    return view('bio.age-verify', compact('bioPage'));
                }
            }
        }
        $this->trackView($bioPage);

        return view('bio.show', compact('bioPage'));
    }

    public function click(BioLink $bioLink)
    {
        // Disabled blocks / unpublished pages must not keep redirecting.
        $bioLink->loadMissing('bioPage');
        $target = SafeContent::url($bioLink->url);
        if (! $bioLink->is_active || ! $bioLink->bioPage?->is_published || $bioLink->bioPage->user?->isSuspended() || $target === '#') {
            abort(404);
        }
        $ipAddress = request()->ip();
        $ua = app(UserAgentService::class)->parse(request()->userAgent());
        $clickData = [
            'ip_address' => $ipAddress,
            'device' => strtolower($ua['device']),
            'browser' => $ua['browser'],
            'referrer' => request()->header('referer'),
            'country' => $this->getCountryFromIp($ipAddress),
        ];
        $bioLink->recordClick($clickData);

        return redirect()->away($target);
    }

    public function qr(string $slug)
    {
        $bioPage = BioPage::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();
        $url = url('/b/'.$bioPage->slug);
        $qrCode = QrCode::format('png')
            ->size(512)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($url);

        return response($qrCode)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', "attachment; filename=\"{$bioPage->slug}-qr.png\"");
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
