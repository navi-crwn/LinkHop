<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A role on HOP ID. Global roles have no app_slug; "admin" with an app_slug
 * makes someone admin of that one app only.
 */
class Role extends Model
{
    public const OWNER = 'owner';

    public const ADMIN = 'admin';

    public const MODERATOR = 'moderator';

    public const SUPPORT = 'support';

    public const GLOBAL = [self::OWNER, self::ADMIN, self::MODERATOR, self::SUPPORT];

    protected $fillable = ['user_id', 'role', 'app_slug', 'granted_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
