<?php

namespace App\Models;

use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AbuseReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'url',
        'email',
        'reason',
        'ip_address',
        'status',
        'source',
    ];

    /**
     * Reports about LinkHop short links only. abuse_reports is shared with BioHop, whose reports about bio pages
     * carry source = 'biohop' and belong in BioHop's own queue. Before the `source` migration has run there is
     * nothing to filter on, so every report counts as a LinkHop one, as it always did.
     */
    public function scopeAboutLinks(Builder $query): Builder
    {
        return Schema::hasColumn($query->getModel()->getTable(), 'source')
            ? $query->where('source', 'linkhop')
            : $query;
    }

    protected static function booted()
    {
        static::created(function ($abuseReport) {
            // Send notification to admin when new abuse report is created
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->notifyAbuseReport([
                    'report_id' => $abuseReport->id,
                    'slug' => $abuseReport->slug,
                    'url' => $abuseReport->url,
                    'email' => $abuseReport->email,
                    'reason' => $abuseReport->reason,
                    'ip_address' => $abuseReport->ip_address,
                    'status' => $abuseReport->status,
                    'reported_at' => $abuseReport->created_at->format('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to send abuse report notification', [
                    'error' => $e->getMessage(),
                    'report_id' => $abuseReport->id,
                ]);
            }
        });
    }
}
