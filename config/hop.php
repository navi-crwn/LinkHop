<?php

/*
|--------------------------------------------------------------------------
| HOP: the family of apps behind hel.ink
|--------------------------------------------------------------------------
| LinkHop is one app of the family. The hub (hel.ink + hub.hel.ink) owns the
| accounts and signs people in here with SSO tickets (hop/auth); this app
| shares its database with the hub (see README, "Database bersama").
|
| This file is the same as the hub's config/hop.php (keep the `apps` list in
| step with it), with `current` set to this app's slug. hop/ui reads it to
| draw the cross-app navigation.
*/
return [
    'brand' => env('HOP_BRAND', 'HOP'),
    'tagline' => env('HOP_TAGLINE', 'Hop Easy Link'),

    // Which app this is (a key of `apps`, or "hub").
    'current' => 'linkhop',

    // The hub (HOP ID) and the public host. Same values as HOP_HUB_URL / PUBLIC_URL in .env.
    'public_url' => rtrim((string) (env('PUBLIC_URL') ?: env('APP_URL', 'http://localhost')), '/'),
    'hub_url' => rtrim((string) env('HOP_HUB_URL', 'https://hub.hel.ink'), '/'),

    // The HOP control center (Imperium): where moderation lives. Null hides the
    // sidebar item and /admin explains where the admin pages went.
    'control_url' => env('HOP_CONTROL_URL'),
    'control_name' => env('HOP_CONTROL_NAME', 'Control center'),

    // Seconds a signed server-to-server request (Imperium -> /api/hop/*, hub -> /internal/*) stays valid.
    'request_max_age' => 120,

    // Seconds a sign-in ticket for an app stays valid (the hub's value; here for reference).
    'ticket_ttl' => 120,

    // The apps, in launcher order. `url` empty: the app is not configured and is
    // skipped everywhere (launcher, SSO, watchdog).
    'apps' => [
        'linkhop' => [
            'name' => 'LinkHop',
            'tagline' => 'Short links, QR codes and click analytics.',
            'url' => env('LINKHOP_URL', 'https://link.hel.ink'),
            'public_prefix' => '/l/',
            'callback' => env('LINKHOP_SSO_CALLBACK', '/auth/sso/callback'),
            'logout' => env('LINKHOP_LOGOUT_PATH', '/auth/sso/logout'),
            'export' => '/settings#backup',
            'health' => '/up',
            'check' => env('LINKHOP_CHECK_URL'),
            'secret' => env('HOP_SSO_SECRET_LINKHOP'),
            'icon' => 'link',
            'color' => 'indigo',
            'repo' => 'https://github.com/navi-crwn/LinkHop',
            'enabled' => (bool) env('LINKHOP_ENABLED', true),
        ],
        'biohop' => [
            'name' => 'BioHop',
            'tagline' => 'One link-in-bio page for everything you do.',
            'url' => env('BIOHOP_URL', 'https://bio.hel.ink'),
            'public_prefix' => '/b/',
            'callback' => env('BIOHOP_SSO_CALLBACK', '/auth/sso/callback'),
            'logout' => env('BIOHOP_LOGOUT_PATH', '/auth/sso/logout'),
            'export' => '/settings#export',
            'health' => '/up',
            'check' => env('BIOHOP_CHECK_URL'),
            'secret' => env('HOP_SSO_SECRET_BIOHOP'),
            'icon' => 'bio',
            'color' => 'fuchsia',
            'repo' => 'https://github.com/navi-crwn/BioHop',
            'enabled' => (bool) env('BIOHOP_ENABLED', true),
        ],
        'pixelhop' => [
            'name' => 'PixelHop',
            'tagline' => 'Image hosting with albums and direct links.',
            'url' => env('PIXELHOP_URL', 'https://p.hel.ink'),
            'public_prefix' => null,
            'callback' => env('PIXELHOP_SSO_CALLBACK', '/auth/helink/callback'),
            'start' => env('PIXELHOP_SSO_START', '/auth/helink'),
            'logout' => env('PIXELHOP_LOGOUT_PATH'),
            'export' => null,
            'health' => '/up',
            'check' => env('PIXELHOP_CHECK_URL'),
            'secret' => env('HOP_SSO_SECRET_PIXELHOP', env('PIXELHOP_SSO_SECRET')),
            'icon' => 'image',
            'color' => 'sky',
            'repo' => null,
            'enabled' => (bool) env('PIXELHOP_ENABLED', true),
        ],
    ],

    // Days a released username stays unavailable to other people.
    'username_hold_days' => 90,

    // Usernames nobody can take: site routes of every app, staff-looking names,
    // and the brands. Lowercase. Prefixes end with "-".
    'reserved_usernames' => [
        'hop', 'hopid', 'hop-id', 'imperium', 'citadel', 'linkhop', 'pixelhop', 'notehop', 'remotehop', 'biohop',
        'helink', 'hel', 'hel-ink', 'hub', 'admin', 'administrator', 'root', 'system', 'moderator', 'mod',
        'staff', 'team', 'official', 'support', 'help', 'abuse', 'report', 'dmca', 'security', 'verify',
        'verified', 'legal', 'billing', 'owner', 'sysadmin', 'webmaster', 'postmaster', 'hostmaster',
        'noreply', 'no-reply', 'mailer-daemon',
        'api', 'app', 'apps', 'about', 'settings', 'login', 'logout', 'register', 'signup', 'signin',
        'dashboard', 'tools', 'docs', 'static', 'assets', 'public', 'storage', 'avatar', 'avatars',
        'profile', 'account', 'accounts', 'user', 'users', 'u', 'b', 'l', 'n', 'i', 'new', 'home', 'explore',
        'search', 'upload', 'uploads', 'album', 'albums', 'image', 'images', 'note', 'notes', 'link',
        'links', 'bio', 'folders', 'tags', 'analytics', 'password', 'reset', 'confirm', 'email', 'sso',
        'auth', 'oauth', 'callback', 'health', 'healthz', 'metrics', 'status', 'contact', 'terms', 'privacy',
        'faq', 'blog', 'news', 'shop', 'store', 'pricing', 'plans', 'download', 'downloads', 'share',
        'embed', 'oembed', 'sitemap', 'robots', 'manifest', 'sw', 'offline', 'raw', 'mock', 'nearby',
        'vault', 'diff', 'in', 'inbox', 'live', 'render', 'i18n', 'legacy', 'announcements', 'launcher',
        'null', 'undefined', 'none', 'nobody', 'anonymous', 'guest', 'me', 'you', 'everyone', 'test', 'demo',
    ],
    'reserved_username_prefixes' => ['hop-', 'hop_', 'admin-', 'admin_', 'official-', 'official_', 'staff-', 'staff_',
        'support-', 'support_', 'team-', 'team_', 'security-', 'security_', 'verify-', 'verified-', 'linkhop-',
        'pixelhop-', 'notehop-', 'remotehop-', 'biohop-', 'helink-', 'imperium-'],
];
