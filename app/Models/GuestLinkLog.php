<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per guest link (config/shortener.php, guest_policy): a keyed hash of the connection, the destination with
 * its tracking query stripped, the slug and when. Read by App\Services\GuestLinkPolicy to count the hourly and the
 * rolling-day cap and to spot many connections reaching one destination. Deleted after
 * guest_policy.retention_days by links:prune-guest-log.
 */
class GuestLinkLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['creator_hash', 'destination', 'slug', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
