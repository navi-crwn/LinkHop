<?php

namespace App\Jobs;

use App\Models\Link;
use App\Models\LinkClick;
use App\Services\GeoIpService;
use App\Services\ProxyDetectionService;
use App\Services\UserAgentService;
use App\Support\ClickSource;
use App\Support\VisitorHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LogLinkClick implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $linkId,
        protected string $ip,
        protected ?string $referer,
        protected ?string $userAgent,
        // true when the redirect already added this visit to the link's count (links with a click limit)
        protected bool $counted = false,
        // which A/B variant (1-based) the redirect chose, null when the link has no split
        protected ?int $variant = null,
        // direct, qr or nfc (ClickSource), taken from ?src= on the short address
        protected string $source = ClickSource::DIRECT
    ) {}

    public function handle(GeoIpService $geoIp): void
    {
        $link = Link::find($this->linkId);
        // Crawlers and link-preview bots don't count as clicks (and must not keep
        // idle guest links alive).
        $agent = app(UserAgentService::class)->parse($this->userAgent);
        if (! $link || $agent['bot']) {
            return;
        }
        // Enrichment is best effort: a failing GeoIP or proxy lookup must never cost
        // the owner a click.
        $geoData = ['country' => null, 'country_name' => null, 'city' => null, 'region' => null, 'provider' => null];
        $proxyData = ['is_proxy' => false, 'type' => null, 'confidence' => 0];
        try {
            $geoData = array_merge($geoData, $geoIp->details($this->ip));
        } catch (\Throwable $e) {
            Log::warning('LogLinkClick geo lookup failed', ['link_id' => $link->id, 'error' => $e->getMessage()]);
        }
        try {
            $proxyData = array_merge($proxyData, app(ProxyDetectionService::class)->detect($this->ip));
        } catch (\Throwable $e) {
            Log::warning('LogLinkClick proxy lookup failed', ['link_id' => $link->id, 'error' => $e->getMessage()]);
        }
        try {
            LinkClick::create([
                'link_id' => $link->id,
                'referer' => $this->referer,
                'ip_address' => $this->ip,
                'visitor_hash' => VisitorHash::daily($this->ip, $this->userAgent),
                'source' => in_array($this->source, ClickSource::ALLOWED, true) ? $this->source : ClickSource::DIRECT,
                'in_app' => $agent['in_app'],
                'country' => $geoData['country'],
                'country_name' => $geoData['country_name'],
                'city' => $geoData['city'],
                'region' => $geoData['region'],
                'isp' => $geoData['provider'],
                'is_proxy' => $proxyData['is_proxy'],
                'proxy_type' => $proxyData['type'],
                'proxy_confidence' => $proxyData['confidence'],
                'user_agent' => substr($this->userAgent ?? 'unknown', 0, 255),
                'variant' => $this->variant,
                'clicked_at' => now(),
            ]);
            if (! $this->counted) {
                $link->increment('clicks');
            }
            $link->last_clicked_at = now();
            $link->saveQuietly();
            Cache::put('queue:heartbeat', now()->toDateTimeString(), now()->addMinutes(5));
        } catch (\Throwable $e) {
            Log::warning('LogLinkClick failed', ['link_id' => $link->id, 'error' => $e->getMessage()]);
        }
    }
}
