<?php

namespace App\Models;

use App\Services\LinkService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Anonymous links live until `guest_idle_days` without a click, and never
     * longer than `guest_max_days` after creation.
     */
    public function isGuestExpired(): bool
    {
        if ($this->user_id !== null || ! $this->created_at) {
            return false;
        }
        $lastActivity = $this->last_clicked_at ?? $this->created_at;

        return $this->created_at->lt(now()->subDays((int) config('shortener.guest_max_days', 30)))
            || $lastActivity->lt(now()->subDays((int) config('shortener.guest_idle_days', 14)));
    }

    /**
     * Guest links past either limit (see isGuestExpired()).
     */
    public function scopeGuestExpired($query)
    {
        return $query->whereNull('user_id')->where(function ($q) {
            $q->where('created_at', '<', now()->subDays((int) config('shortener.guest_max_days', 30)))
                ->orWhereRaw('COALESCE(last_clicked_at, created_at) < ?', [now()->subDays((int) config('shortener.guest_idle_days', 14))]);
        });
    }

    public function requiresPassword(): bool
    {
        return filled($this->password_hash);
    }
}
