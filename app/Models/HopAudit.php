<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One admin action taken through the signed HOP admin API (Imperium). */
class HopAudit extends Model
{
    protected $table = 'hop_audit';

    public $timestamps = false;

    protected $fillable = ['actor', 'app_slug', 'action', 'target_type', 'target_id', 'reason', 'params'];

    protected function casts(): array
    {
        return ['params' => 'array', 'created_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->actor,
            'app_slug' => $this->app_slug,
            'action' => $this->action,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'reason' => $this->reason,
            'params' => $this->params ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
