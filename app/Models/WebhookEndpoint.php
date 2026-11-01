<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    protected $fillable = ['user_id', 'url', 'secret', 'events', 'is_active', 'failure_streak', 'last_delivery_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivery_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function wants(string $event): bool
    {
        return $this->events === null || $this->events === [] || in_array($event, $this->events, true);
    }

    /** The address without a path or query, for lists: the rest may hold a token. */
    public function displayHost(): string
    {
        $parts = parse_url($this->url);

        return ($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
