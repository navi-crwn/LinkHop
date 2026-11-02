<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class IpBan extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'reason',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** The cache key CheckIpBan reads; everything that changes a ban forgets this same key. */
    public static function cacheKey(string $ip): string
    {
        return 'ip-ban:'.$ip;
    }

    public static function forgetCache(string $ip): void
    {
        Cache::forget(self::cacheKey($ip));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }
}
