<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Words that cannot be a short link or bio slug: `shortener.reserved_slugs` plus the first
 * path segment of every app route (hel.ink/api-docs, /analytics, /up, ...). An app route
 * wins over the /{slug} catch-all, so a link with such a slug would never open. The route
 * part is read from the router, so a route added later is covered without editing config.
 * Matching ignores case.
 */
class ReservedSlugs
{
    /** @var array<string, true>|null */
    private static ?array $routeSegments = null;

    public static function isReserved(?string $slug): bool
    {
        $slug = strtolower(trim((string) $slug));
        if ($slug === '') {
            return false;
        }

        return isset(self::routeSegments()[$slug])
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

    /** @return array<string, true> */
    public static function routeSegments(): array
    {
        if (self::$routeSegments !== null) {
            return self::$routeSegments;
        }
        $segments = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $first = strtolower(explode('/', trim($route->uri(), '/'))[0]);
            if ($first !== '' && ! str_contains($first, '{')) {
                $segments[$first] = true;
            }
        }

        // Routes are registered once per process (and cached by route:cache), so keep the result.
        return self::$routeSegments = $segments;
    }

    public static function flush(): void
    {
        self::$routeSegments = null;
    }
}
