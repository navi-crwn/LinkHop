<?php

namespace App\Support;

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
}
