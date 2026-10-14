<?php

namespace App\Models;

use App\Support\VisitorHash;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'link_id',
        'referer',
        'ip_address',
        'country',
        'country_name',
        'city',
        'region',
        'isp',
        'is_proxy',
        'proxy_type',
        'proxy_confidence',
        'user_agent',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'is_proxy' => 'boolean',
    ];

    protected $hidden = ['ip_address'];

    /** Stored as a keyed hash only; see VisitorHash. */
    protected function ipAddress(): Attribute
    {
        return Attribute::set(fn (?string $value) => VisitorHash::make($value));
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }
}
