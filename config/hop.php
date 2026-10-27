<?php

/*
|--------------------------------------------------------------------------
| HOP ID
|--------------------------------------------------------------------------
| This app is the account server ("HOP ID") for the HOP family of apps:
| LinkHop (this one), PixelHop, NoteHop, RemoteHop and whatever comes next.
| The apps themselves are rows in the `apps` table (see HopApp).
*/
return [
    // Shown in buttons: "Login with HOP", "Join HOP".
    'brand' => env('HOP_BRAND', 'HOP'),

    // The HOP control center (Imperium): where moderation and settings live now
    // that the built-in admin pages are gone. /admin sends admins there; the
    // sidebar shows it under this name. Null: /admin explains where it went.
    'control_url' => env('HOP_CONTROL_URL'),
    'control_name' => env('HOP_CONTROL_NAME', 'Control center'),

    // Seconds a sign-in ticket for an app stays valid.
    'ticket_ttl' => 120,

    // Seconds a signed server-to-server request (apps <-> HOP ID) stays valid.
    'request_max_age' => 120,

    // Days a released username stays unavailable to other people.
    'username_hold_days' => 90,

    // Usernames nobody can take: site routes of every app, staff-looking names,
    // and the brands. Lowercase. Prefixes end with "-".
    'reserved_usernames' => [
        // HOP family and staff
        'hop', 'hopid', 'hop-id', 'imperium', 'linkhop', 'pixelhop', 'notehop', 'remotehop', 'biohop',
        'helink', 'hel', 'hel-ink', 'admin', 'administrator', 'root', 'system', 'moderator', 'mod',
        'staff', 'team', 'official', 'support', 'help', 'abuse', 'report', 'dmca', 'security', 'verify',
        'verified', 'legal', 'billing', 'owner', 'sysadmin', 'webmaster', 'postmaster', 'hostmaster',
        'noreply', 'no-reply', 'mailer-daemon',
        // Routes of the apps
        'api', 'app', 'apps', 'about', 'settings', 'login', 'logout', 'register', 'signup', 'signin',
        'dashboard', 'tools', 'docs', 'static', 'assets', 'public', 'storage', 'avatar', 'avatars',
        'profile', 'account', 'accounts', 'user', 'users', 'u', 'b', 'n', 'i', 'new', 'home', 'explore',
        'search', 'upload', 'uploads', 'album', 'albums', 'image', 'images', 'note', 'notes', 'link',
        'links', 'bio', 'folders', 'tags', 'analytics', 'password', 'reset', 'confirm', 'email', 'sso',
        'auth', 'oauth', 'callback', 'health', 'healthz', 'metrics', 'status', 'contact', 'terms', 'privacy',
        'faq', 'blog', 'news', 'shop', 'store', 'pricing', 'plans', 'download', 'downloads', 'share',
        'embed', 'oembed', 'sitemap', 'robots', 'manifest', 'sw', 'offline', 'raw', 'mock', 'nearby',
        'vault', 'diff', 'in', 'inbox', 'live', 'render', 'i18n', 'legacy',
        // Generic words that mislead
        'null', 'undefined', 'none', 'nobody', 'anonymous', 'guest', 'me', 'you', 'everyone', 'test', 'demo',
    ],
    'reserved_username_prefixes' => ['hop-', 'hop_', 'admin-', 'admin_', 'official-', 'official_', 'staff-', 'staff_',
        'support-', 'support_', 'team-', 'team_', 'security-', 'security_', 'verify-', 'verified-', 'linkhop-',
        'pixelhop-', 'notehop-', 'remotehop-', 'helink-', 'imperium-'],
];
