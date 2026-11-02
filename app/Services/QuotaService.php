<?php

namespace App\Services;

use App\Models\Link;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QuotaService
{
    public function incrementIpUsage(string $ip): array
    {
        $minuteKey = "quota:ip:minute:{$ip}:".now()->format('YmdHi');
        $dayKey = "quota:ip:day:{$ip}:".now()->format('Ymd');
        $minute = Cache::increment($minuteKey);
        $day = Cache::increment($dayKey);
        Cache::put($minuteKey, $minute, now()->addMinute());
        Cache::put($dayKey, $day, now()->endOfDay());

        return [$minute, $day];
    }

    public function checkGuestLimits(string $ip): bool
    {
        $limits = config('limits');
        [$perMinute, $perDay] = $this->incrementIpUsage($ip);
        if ($perMinute > $limits['ip']['creates_per_minute'] || $perDay > $limits['ip']['creates_per_day']) {
            return false;
        }
        $todayKey = "quota:guest:day:{$ip}:".now()->format('Ymd');
        $totalKey = "quota:guest:total:{$ip}";
        $today = Cache::increment($todayKey);
        $total = Cache::increment($totalKey);
        Cache::put($todayKey, $today, now()->endOfDay());
        Cache::put($totalKey, $total, now()->addDays(7));
        if ($today > $limits['guest']['max_links_per_day'] || $total > $limits['guest']['max_total_links']) {
            return false;
        }

        return true;
    }

    public function checkUserLimits(int $userId): bool
    {
        $limits = config('limits.user');
        $dayCount = Link::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();
        $activeCount = Link::where('user_id', $userId)
            ->where('status', Link::STATUS_ACTIVE)
            ->count();

        return $dayCount < $limits['max_links_per_day'] && $activeCount < $limits['max_active_links'];
    }

    /**
     * Run $create (the insert of one link) only while the account is within its limits, and
     * return what it returned, or null when the account is over. The check and the insert share
     * one transaction that first locks the user's row, so two requests for the last free slot
     * take turns: the second one counts the first one's link. Keep $create to the insert itself.
     *
     * @template T
     *
     * @param  Closure(): T  $create
     * @return T|null
     */
    public function createWithinLimits(int $userId, Closure $create): mixed
    {
        return DB::transaction(function () use ($userId, $create) {
            User::whereKey($userId)->lockForUpdate()->value('id');

            return $this->checkUserLimits($userId) ? $create() : null;
        });
    }

    /** How many more links the account may create right now (today's allowance and the active-link cap, whichever is smaller). */
    public function remainingFor(int $userId): int
    {
        $limits = config('limits.user');
        $dayCount = Link::where('user_id', $userId)->whereDate('created_at', today())->count();
        $activeCount = Link::where('user_id', $userId)->where('status', Link::STATUS_ACTIVE)->count();

        return max(0, min($limits['max_links_per_day'] - $dayCount, $limits['max_active_links'] - $activeCount));
    }
}
