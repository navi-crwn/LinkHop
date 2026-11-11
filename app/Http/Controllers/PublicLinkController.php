<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShortenLinkRequest;
use App\Models\IpWatchlist;
use App\Models\Link;
use App\Services\GuestLinkPolicy;
use App\Services\LinkService;
use App\Services\TurnstileService;
use App\Support\GuestClaim;
use App\Support\GuestDestination;
use Illuminate\Support\Facades\Log;

class PublicLinkController extends Controller
{
    use HandlesSlugs;

    public function __construct(
        private readonly TurnstileService $turnstile,
        private readonly LinkService $links,
        private readonly GuestLinkPolicy $policy,
    ) {}

    /** link.hel.ink/: LinkHop's own landing for guests, the dashboard once signed in. */
    public function index()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('landing', [
            'shortened' => session('shortened_link'),
        ]);
    }

    public function store(ShortenLinkRequest $request)
    {
        if (! $this->turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()->withErrors(['turnstile' => 'Security verification failed. Please try again.'])->withInput();
        }
        $data = $request->validated();
        // The guest policy (config/shortener.php, guest_policy): refuse, or allow with a warning page until scanned.
        $decision = $this->policy->decide($data['target_url'], $request->ip());
        if (! $decision['allowed']) {
            Log::info('guest link refused', ['reason' => $decision['reason'], 'host' => parse_url($data['target_url'], PHP_URL_HOST)]);

            return back()->withErrors(['target_url' => $decision['message']])->withInput();
        }
        $watchlistEntry = IpWatchlist::where('ip_address', $request->ip())->first();
        if ($watchlistEntry) {
            $watchlistEntry->increment('attempt_count');
            $watchlistEntry->update([
                'last_attempt_at' => now(),
                'reason' => ($watchlistEntry->reason ?? '').' | Used public link creation on '.now()->format('Y-m-d H:i:s'),
            ]);
        }
        [$claimToken, $claimCookie] = GuestClaim::issue($request);
        $link = Link::create([
            'claim_token_hash' => GuestClaim::hash($claimToken),
            'target_url' => $data['target_url'],
            'slug' => $this->prepareSlug($data['slug'] ?? null),
            'status' => Link::STATUS_ACTIVE,
            'safety_status' => $decision['safety_status'],
            'creator_hash' => $decision['creator_hash'],
            'content_warning' => $data['content_warning'] ?? 'none',
            'warning_reason' => $data['warning_reason'] ?? null,
        ]);
        $this->links->generateQr($link);
        $this->links->forgetCache($link);
        // What a guest made, for the hourly and rolling-day caps and for burst detection across days.
        $this->policy->remember($link->target_url, $decision['creator_hash'], $link->slug);
        if (! empty($decision['burst'])) {
            $this->policy->flagBurst($link, $link->target_url);
            Log::info('guest link held: destination burst', ['destination' => GuestDestination::normalize($link->target_url)]);
        }

        return redirect()
            ->route('home')
            ->with('shortened_link', $link)
            ->withCookie($claimCookie);
    }
}
