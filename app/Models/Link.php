<?php

namespace App\Models;

use App\Services\LinkService;
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
        'status',
        'clicks',
        'last_clicked_at',
        'folder_id',
        'password_hash',
        'expires_at',
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
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

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
            ->whereNot(fn ($q) => $q->guestExpired());
    }

    /**
     * The opposite of live(): disabled, expired or guest-expired links.
     */
    public function scopeEnded($query)
    {
        return $query->where(fn ($q) => $q->where('links.status', '!=', self::STATUS_ACTIVE)
            ->orWhere('links.expires_at', '<=', now())
            ->orWhere(fn ($g) => $g->guestExpired()));
    }

    public function getShortUrlAttribute(): string
    {
        return str_replace('www.', '', url($this->slug));
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
    }

    public function isExpired(): bool
    {
        return ($this->expires_at !== null && now()->greaterThan($this->expires_at))
            || $this->isGuestExpired();
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

    public function requiresPassword(): bool
    {
        return filled($this->password_hash);
    }
}
