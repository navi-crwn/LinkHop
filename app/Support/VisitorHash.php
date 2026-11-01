<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Click logs keep a keyed hash of the visitor IP, never the address itself:
 * enough to count unique visitors, useless for locating a visitor.
 */
class VisitorHash
{
    public static function make(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }
        // Already hashed (e.g. a model re-saved after loading).
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return $ip;
        }

        // 40 hex chars fit the existing varchar(45) ip_address columns.
        return substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 40);
    }

    /**
     * A visitor for one day only: the key changes at 00:00 UTC, so the same person gets another hash tomorrow
     * and visits cannot be followed from day to day. Counting distinct values gives unique visitors per day.
     * Null without a usable IP.
     */
    public static function daily(?string $ip, ?string $userAgent, ?CarbonInterface $at = null): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $day = ($at ?? now())->copy()->utc()->format('Y-m-d');
        $dayKey = hash_hmac('sha256', 'visitor-day:'.$day, (string) config('app.key'));

        return substr(hash_hmac('sha256', $ip."\n".$userAgent, $dayKey), 0, 40);
    }
}
