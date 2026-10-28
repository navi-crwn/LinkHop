<?php

namespace App\Support;

/**
 * Words that cannot be a short link or bio slug: `shortener.reserved_slugs`,
 * which includes the path prefixes of the HOP family (`l`, `b`, `api`). Short
 * links live under /l/ and bio pages under /b/, so app routes (hel.ink/analytics,
 * /api-docs, ...) no longer compete with slugs and need not be reserved.
 * Matching ignores case.
 */
class ReservedSlugs
{
    /** Always reserved, whatever the config says: the prefixes the router hands to the apps. */
    public const PREFIXES = ['l', 'b', 'api'];

    public static function isReserved(?string $slug): bool
    {
        $slug = strtolower(trim((string) $slug));
        if ($slug === '') {
            return false;
        }

        return in_array($slug, self::PREFIXES, true)
            || in_array($slug, array_map('strtolower', config('shortener.reserved_slugs', [])), true);
    }

    /** Validation rule; $current is the slug the record already has, which stays allowed. */
    public static function rule(?string $current = null, string $message = 'This slug is reserved.'): \Closure
    {
        return function ($attribute, $value, $fail) use ($current, $message) {
            if (is_string($value) && strcasecmp($value, (string) $current) !== 0 && self::isReserved($value)) {
                $fail($message);
            }
        };
    }
}
