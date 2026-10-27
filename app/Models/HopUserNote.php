<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An admin's note on an account, written through the HOP admin API. */
class HopUserNote extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'author', 'text'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
