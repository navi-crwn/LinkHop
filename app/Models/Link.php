<?php

namespace App\Models;

use App\Services\LinkService;
use App\Support\ClickSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Link extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'target_url',
        'user_id',
        'creator_hash',
        'status',
        'safety_status',
        'content_warning',
        'warning_reason',
        'clicks',
        'last_clicked_at',
        'folder_id',
        'password_hash',
        'expires_at',
        'max_clicks',
        'starts_at',
        'fallback_url',
        'routing_rules',
        'ab_variants',
        'app_links',
        'redirect_type',
        'custom_title',
        'custom_description',
        'custom_image_url',
        'qr_code_path',
        'qr_fg_color',
        'qr_bg_color',
        'qr_logo_url',
    ];

    protected $casts = [
        'clicks' => 'integer',
        'last_clicked_at' => 'datetime',
        'expires_at' => 'datetime',
        'starts_at' => 'datetime',
        'max_clicks' => 'integer',
        'routing_rules' => 'array',
        'ab_variants' => 'array',
        'app_links' => 'array',
        'last_checked_at' => 'datetime',
        'last_safety_scan_at' => 'datetime',
        'broken_since' => 'datetime',
        'check_failures' => 'integer',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** safety_status of a guest link to a site the allowlist does not know: a warning page until abuse:scan clears it. */
    public const SAFETY_PENDING = 'pending';

    public const SAFETY_SAFE = 'safe';

    public const SAFETY_FLAGGED = 'flagged';

    public const WARNING_NONE = 'none';

    public const WARNING_SENSITIVE = 'sensitive';

    public const WARNING_ADULT = 'adult';

    public function isAdult(): bool
    {
        return $this->content_warning === self::WARNING_ADULT;
    }

    public function isSensitive(): bool
    {
        return $this->content_warning === self::WARNING_SENSITIVE;
    }

    public function requiresContentWarning(): bool
    {
        return in_array($this->content_warning, [self::WARNING_SENSITIVE, self::WARNING_ADULT], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Links that still resolve: active, not past expires_at, not guest-expired.
     */
    public function scopeLive($query)
    {
        return $query->where('links.status', self::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('links.expires_at')->orWhere('links.expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('links.starts_at')->orWhere('links.starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('links.max_clicks')->orWhereColumn('links.clicks', '<', 'links.max_clicks'))
            ->whereNot(fn ($q) => $q->guestExpired());
    }

    /**
     * The opposite of live(): disabled, expired or guest-expired links.
     */
    public function scopeEnded($query)
    {
        return $query->where(fn ($q) => $q->where('links.status', '!=', self::STATUS_ACTIVE)
            ->orWhere('links.expires_at', '<=', now())
            ->orWhere(fn ($m) => $m->whereNotNull('links.max_clicks')->whereColumn('links.clicks', '>=', 'links.max_clicks'))
            ->orWhere(fn ($g) => $g->guestExpired()));
    }

    /**
     * One address, compared the way people mean it: scheme and host in lower case,
     * no fragment, no trailing slash on a bare path.
     */
    public static function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! $parts || empty($parts['host'])) {
            return trim($url);
        }
        $path = $parts['path'] ?? '';
        $path = $path === '/' ? '' : rtrim($path, '/');

        return strtolower($parts['scheme'] ?? 'https').'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .$path
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** The public form of the link: PUBLIC_URL/l/{slug} (see config/shortener.php). */
    public function getShortUrlAttribute(): string
    {
        return str_replace('www.', '', public_url('l/'.$this->slug));
    }

    /** The short address as printed in QR codes: ?src=qr tells the redirect that the visit was a scan. */
    public function getQrUrlAttribute(): string
    {
        return ClickSource::tag($this->short_url);
    }

    /** The form used before the move to /l/, answered with a 301 until shortener.legacy_slug_until. */
    public function getLegacyUrlAttribute(): string
    {
        return str_replace('www.', '', public_url($this->slug));
    }

    protected static function booted(): void
    {
        // RedirectController caches links by slug: drop the cache on every change so
        // disabled/deleted/edited links stop resolving immediately, whatever the caller.
        $forget = function (Link $link) {
            app(LinkService::class)->forgetCacheBySlug($link->slug);
            if ($link->isDirty('slug') || $link->wasChanged('slug')) {
                app(LinkService::class)->forgetCacheBySlug((string) $link->getOriginal('slug'));
            }
        };
        static::saved($forget);
        static::deleted($forget);
        // A new destination starts the destination monitor over: nothing is known about it yet.
        static::updating(function (Link $link) {
            if ($link->isDirty('target_url')) {
                $link->last_checked_at = null;
                $link->last_status = null;
                $link->check_failures = 0;
                $link->broken_since = null;
            }
        });
    }

    public function isExpired(): bool
    {
        return ($this->expires_at !== null && now()->greaterThan($this->expires_at))
            || $this->isGuestExpired();
    }

    /** The destination monitor found the destination not answering (see links:check-destinations). */
    public function isBroken(): bool
    {
        return $this->broken_since !== null;
    }

    /** Not live yet: a start time is set and has not come. */
    public function isNotStarted(): bool
    {
        return $this->starts_at !== null && now()->lessThan($this->starts_at);
    }

    /** Out of clicks: a limit is set and that many people have already come. */
    public function isLimitReached(?int $counted = null): bool
    {
        return $this->max_clicks !== null && ($counted ?? (int) $this->clicks) >= $this->max_clicks;
    }

    /** Over for good or for now: expired or out of clicks (a link that has not started is not "ended"). */
    public function hasEnded(): bool
    {
        return $this->isExpired() || $this->isLimitReached();
    }

    /**
     * Anonymous links count whole days in `shortener.guest_timezone` and stop
     * working at 00:00: after `guest_idle_days` full days without a click, and
     * after day `guest_max_days` (the day they were created is day 1).
     */
    public function isGuestExpired(): bool
    {
        if ($this->user_id !== null || ! $this->created_at) {
            return false;
        }
        [$createdBefore, $idleBefore] = self::guestCutoffs();

        return $this->created_at->lt($createdBefore)
            || ($this->last_clicked_at ?? $this->created_at)->lt($idleBefore);
    }

    /**
     * Guest links past either limit (see isGuestExpired()).
     */
    public function scopeGuestExpired($query)
    {
        [$createdBefore, $idleBefore] = self::guestCutoffs();
        $tz = config('app.timezone');

        return $query->whereNull('user_id')->where(function ($q) use ($createdBefore, $idleBefore, $tz) {
            $q->where('created_at', '<', $createdBefore->copy()->setTimezone($tz)->toDateTimeString())
                ->orWhereRaw('COALESCE(last_clicked_at, created_at) < ?', [$idleBefore->copy()->setTimezone($tz)->toDateTimeString()]);
        });
    }

    /**
     * Midnight-based cutoffs for guest links: created before the first, or last
     * clicked (else created) before the second, means expired.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function guestCutoffs(): array
    {
        $today = now(config('shortener.guest_timezone', config('app.timezone')))->startOfDay();

        return [
            $today->copy()->subDays(max(0, (int) config('shortener.guest_max_days', 30) - 1)),
            $today->copy()->subDays((int) config('shortener.guest_idle_days', 14)),
        ];
    }

    /** A guest link to an unknown site that no scan has cleared yet: visitors see a warning page first. */
    public function isPendingSafety(): bool
    {
        return $this->safety_status === self::SAFETY_PENDING;
    }

    public function requiresPassword(): bool
    {
        return filled($this->password_hash);
    }

    /**
     * True when what a visitor meets can still change after the redirect: a warning, a password, a routing
     * rule, the A/B split, a click limit, a start or end time, a fallback address, the preview page,
     * "open in the app", or a pending safety check. A browser that stored a permanent redirect keeps the old
     * answer, so these links must answer with a temporary one (see RedirectController).
     */
    public function hasDynamicFeatures(): bool
    {
        return $this->requiresContentWarning()
            || $this->requiresPassword()
            || $this->isPendingSafety()
            || (bool) $this->enable_preview
            || $this->max_clicks !== null
            || $this->expires_at !== null
            || $this->starts_at !== null
            || filled($this->fallback_url)
            || ! empty($this->routing_rules)
            || ! empty($this->ab_variants)
            || ! empty($this->app_links);
    }
}
