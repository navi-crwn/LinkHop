<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The slug of a deleted guest link. Visitors get an "expired" page instead of a
 * 404, and nobody can register the slug again until `reusable_after`.
 */
class RetiredSlug extends Model
{
    public $timestamps = false;

    protected $fillable = ['slug', 'retired_at', 'reusable_after'];

    protected $casts = [
        'retired_at' => 'datetime',
        'reusable_after' => 'datetime',
    ];

    public static function retire(string $slug): void
    {
        static::updateOrCreate(['slug' => strtolower($slug)], [
            'retired_at' => now(),
            'reusable_after' => now()->addDays((int) config('shortener.retired_slug_days', 90)),
        ]);
    }

    public static function isBlocked(string $slug): bool
    {
        return static::where('slug', strtolower($slug))->where('reusable_after', '>', now())->exists();
    }
}
