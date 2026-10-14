<?php

return [
    /*
    | Comma-separated proxy IPs/CIDRs in front of the app (e.g. Cloudflare ranges or
    | 127.0.0.1 for a local nginx), or "*" when the origin is only reachable through
    | the proxy. Without this, request()->ip() is the proxy's IP and every per-IP
    | limit, ban and quota applies to all visitors at once.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'name' => env('APP_NAME', 'HEL.ink'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'UTC',
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
