<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A finding recorded by `abuse:scan` against a link, bio page, bio block or user.
 */
class AbuseFlag extends Model
{
    public const ACTION_FLAGGED = 'flagged';

    public const ACTION_DISABLED = 'disabled';

    public const ACTION_SUSPENDED = 'suspended';

    public const ACTION_PURGED = 'purged';

    public const STATUS_OPEN = 'open';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'user_id',
        'score',
        'signals',
        'action',
        'status',
        'summary',
    ];

    protected $casts = [
        'signals' => 'array',
        'score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * True while a takedown on this item stands (not dismissed by an admin):
     * owners may not switch it back on.
     */
    public static function locks(string $type, int $id): bool
    {
        return static::where('subject_type', $type)->where('subject_id', $id)
            ->where('action', self::ACTION_DISABLED)->where('status', '!=', self::STATUS_DISMISSED)->exists();
    }

    public function isCsam(): bool
    {
        return in_array('csam', $this->signals ?? [], true);
    }
}
