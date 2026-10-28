<?php

// Guest links: deleted after 14 days in a row without a click, and never live
// longer than 30 days, whatever the server's .env says (it can only shorten them).
$guestMaxDays = min(30, max(1, (int) env('GUEST_LINK_MAX_DAYS', 30)));

return [
    /*
    |--------------------------------------------------------------------------
    | Public URL
    |--------------------------------------------------------------------------
    |
    | Where short links and bio pages are reached by the public (hel.ink), which
    | can differ from APP_URL once the dashboard lives on link.hel.ink. Every
    | public URL (short_url, QR codes, og:url, the sitemap, the API) is built
    | from it through the public_url() helper. Defaults to APP_URL.
    |
    */
    'public_url' => rtrim((string) (env('PUBLIC_URL') ?: env('APP_URL', 'http://localhost')), '/'),

    /*
    |--------------------------------------------------------------------------
    | Legacy short link form
    |--------------------------------------------------------------------------
    |
    | Short links moved from hel.ink/{slug} to hel.ink/l/{slug}. Until this date
    | the old form answers with a 301 to the new one and the dashboard shows the
    | announcement; after it the old form is retired (the redirect rule is
    | removed at the router). ISO date, in APP_TIMEZONE.
    |
    */
    'legacy_slug_until' => env('LEGACY_SLUG_UNTIL', '2026-11-27'),

    /*
    |--------------------------------------------------------------------------
    | Guest (anonymous) links
    |--------------------------------------------------------------------------
    |
    | Links created without an account count in whole calendar days in
    | `guest_timezone` and stop working at 00:00:
    |  - after `guest_idle_days` full days in a row without a click (the day of
    |    the last click, or of creation, is not counted), or
    |  - after day `guest_max_days`, counting the day it was created as day 1.
    | `links:prune-guest` deletes them right after midnight.
    |
    */
    'guest_idle_days' => min(14, $guestMaxDays, max(1, (int) env('GUEST_LINK_IDLE_DAYS', 14))),
    'guest_max_days' => $guestMaxDays,
    'guest_timezone' => env('GUEST_LINK_TIMEZONE') ?: env('APP_TIMEZONE', 'UTC'),

    // A deleted guest link's slug shows an "expired" page and cannot be taken
    // again for this many days, so old shared copies can't be hijacked.
    'retired_slug_days' => max(1, (int) env('RETIRED_SLUG_DAYS', 90)),

    /*
    |--------------------------------------------------------------------------
    | Reserved Slugs
    |--------------------------------------------------------------------------
    |
    | These slugs are reserved for system use and cannot be used for short links.
    | Includes single/double characters and common system routes.
    |
    */
    'reserved_slugs' => [
        // Path prefixes of the HOP family: hel.ink/l/ (short links), /b/ (bio), /api/.
        'l', 'b', 'api',
        // Single characters (reserved for future use)
        'a', 'i', 'x', 'v',
        // Two characters - Common brands & abbreviations
        'fb', 'ig', 'tw', 'yt', 'go', 'my', 'id', 'us', 'uk', 'cn',
        'ai', 'io', 'js', 'db', 'ui', 'ux', 'qa', 'hr', 'pr', 'pm',
        // Three+ characters - System routes
        'api', 'app', 'dev', 'www', 'cdn', 'img', 'css', 'old', 'new',
        'admin', 'login', 'logout', 'register', 'dashboard', 'settings',
        'password', 'reset', 'verify', 'confirm', 'email',
        'links', 'link', 'shorten', 'url', 'short',
        'folders', 'folder', 'tags', 'tag',
        'user', 'users', 'profile', 'account',
        // Marketing & Public pages
        'about', 'help', 'faq', 'contact', 'support',
        'terms', 'privacy', 'legal', 'dmca',
        'blog', 'news', 'press', 'media',
        'pricing', 'plans', 'pro', 'free', 'premium',
        'features', 'products', 'docs', 'guide',
        // Utility routes
        'health', 'status', 'test', 'demo',
        'assets', 'static', 'public', 'storage',
        'upload', 'download', 'export', 'import',
        'report', 'abuse', 'flag', 'spam', 'check',
        // Security & Scam Prevention
        'official', 'verified', 'security', 'secure', 'auth', 'authentication',
        'update', 'upgrade', 'billing', 'payment', 'invoice', 'receipt',
        'wallet', 'crypto', 'bitcoin', 'ethereum', 'bank', 'banking',
        'paypal', 'stripe', 'cashapp', 'venmo', 'zelle',
        // Major Brands (Anti-Impersonation)
        'google', 'gmail', 'youtube', 'facebook', 'instagram', 'whatsapp', 'messenger',
        'twitter', 'x', 'tiktok', 'linkedin', 'pinterest', 'snapchat', 'telegram',
        'microsoft', 'apple', 'icloud', 'amazon', 'netflix', 'spotify', 'twitch',
        'discord', 'slack', 'zoom', 'adobe', 'dropbox', 'mega',
        'helink', 'helink-official', 'helink-support', 'helink-admin',
    ],
    /*
    |--------------------------------------------------------------------------
    | Slug Length Settings
    |--------------------------------------------------------------------------
    |
    | random_slug_length: Length for auto-generated slugs (default: 6)
    | min_custom_slug_length: Minimum length for user-defined custom slugs
    |
    */
    'random_slug_length' => 6,
    'min_custom_slug_length' => 3,
    /*
    |--------------------------------------------------------------------------
    | Analytics Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to retain click analytics data
    |
    */
    'analytics_days' => 30,
];
