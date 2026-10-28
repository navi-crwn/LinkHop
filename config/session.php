<?php

return [
    'driver' => env('SESSION_DRIVER', 'database'),
    // Minutes of inactivity before a session ends; AutoLogout uses the same value.
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', true),
    'encrypt' => env('SESSION_ENCRYPT', false),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    // Unique per HOP app: hel.ink, link.hel.ink and bio.hel.ink share the parent
    // domain, so a shared cookie name would make the apps overwrite each other's session.
    'cookie' => env('SESSION_COOKIE', 'linkhop_session'),
    'path' => env('SESSION_PATH', '/'),
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', str_starts_with((string) env('APP_URL'), 'https://')),
    'http_only' => env('SESSION_HTTP_ONLY', true),
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),
];
