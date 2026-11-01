<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = ['webhook_endpoint_id', 'uuid', 'event', 'payload', 'status', 'attempts', 'http_code', 'error', 'next_attempt_at'];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime'];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
