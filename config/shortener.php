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
    | Guest policy
    |--------------------------------------------------------------------------
    |
    | What a link made without an account may point at, on top of the safety
    | rules that apply to everyone (hop/safety). Accounts are not affected by
    | any of this; abuse:scan scores every link the same way.
    |
    |  - allowlist: destinations on these domains (and their subdomains) are
    |    taken as they are. Any other destination of a guest gets
    |    safety_status = pending and a warning page until the hourly scan
    |    clears it (pending_unknown_hosts).
    |  - min_domain_age_days: a destination whose domain was registered fewer
    |    days ago than this is refused for guests (RDAP lookup, cached). 0
    |    turns the rule off. When the lookup fails or the registry does not
    |    say, unknown_age_is_young decides. The lookup is one request per
    |    domain to rdap.org (which forwards to the registry) and never
    |    touches the destination itself.
    |  - refuse_random_subdomain: a machine-made label left of the domain
    |    (mjdgdqgk.example.com) is refused for guests.
    |  - refuse_phishing_shape: the shape of a known phishing kit's landing
    |    address (random subdomain or path, r=/t= tracking parameters, a
    |    throwaway TLD) is refused for guests. App\Support\PhishingShape.
    |  - per_ip_per_day: guest links one connection may make per calendar
    |    day (counted in the database, next to the per-minute limiter).
    |  - weights: what the LinkHop-only signals add to a link's abuse score.
    |
    */
    'guest_policy' => [
        'enabled' => (bool) env('GUEST_POLICY_ENABLED', true),
        'pending_unknown_hosts' => (bool) env('GUEST_PENDING_UNKNOWN_HOSTS', true),
        'min_domain_age_days' => max(0, (int) env('GUEST_MIN_DOMAIN_AGE_DAYS', 30)),
        'domain_age_lookup' => (bool) env('GUEST_DOMAIN_AGE_LOOKUP', true),
        'unknown_age_is_young' => (bool) env('GUEST_UNKNOWN_AGE_IS_YOUNG', true),
        'refuse_random_subdomain' => (bool) env('GUEST_REFUSE_RANDOM_SUBDOMAIN', true),
        'refuse_phishing_shape' => (bool) env('GUEST_REFUSE_PHISHING_SHAPE', true),
        'per_ip_per_day' => max(0, (int) env('GUEST_LINKS_PER_IP_PER_DAY', 20)),
        // Caps that do not restart at midnight, counted from guest_link_logs: a burst in one hour, and a rolling
        // day that follows a connection across midnight. 0 turns either off. Conservative defaults.
        'per_ip_per_hour' => max(0, (int) env('GUEST_LINKS_PER_IP_PER_HOUR', 6)),
        'per_ip_per_rolling_day' => max(0, (int) env('GUEST_LINKS_PER_IP_PER_ROLLING_DAY', 25)),
        // Many connections to one destination in a short window: the shape of a kit handing out a single address.
        // A new guest link to that destination is held behind the warning page and the destination is flagged for
        // review; nothing is suspended. 0 turns it off.
        'burst_ips' => max(0, (int) env('GUEST_BURST_IPS', 5)),
        'burst_window_minutes' => max(1, (int) env('GUEST_BURST_WINDOW_MINUTES', 60)),
        // guest_link_logs rows are deleted after this many days by links:prune-guest-log.
        'retention_days' => max(1, (int) env('GUEST_LOG_RETENTION_DAYS', 14)),
        // RDAP: how long a registration date is remembered, and how long a failed lookup is not retried.
        'rdap_url' => env('GUEST_RDAP_URL', 'https://rdap.org/domain/'),
        'rdap_cache_days' => 7,
        'rdap_failure_minutes' => 60,
        'rdap_timeout' => 4,
        // Hosts of phishing kits seen on this install (links:sweep-phishing --hosts adds more for one run).
        'kit_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('GUEST_KIT_HOSTS', 'fnjphdp.com,bgp09.icu'))))),
        'weights' => [
            'phishing_shape' => (int) env('GUEST_WEIGHT_PHISHING_SHAPE', 60),
            'young_domain' => (int) env('GUEST_WEIGHT_YOUNG_DOMAIN', 20),
        ],
        // Daily digest (links:guest-digest): a domain with at least this many new guest links in a day is a spike.
        'digest_spike' => max(1, (int) env('GUEST_DIGEST_SPIKE', 10)),
        // Well-known destinations: taken from a guest without a warning page. GUEST_ALLOWLIST_EXTRA adds more.
        'allowlist' => array_values(array_unique(array_filter(array_map('trim', array_merge([
            'hel.ink',
            'example.com', 'example.org', 'example.net',
            'google.com', 'youtube.com', 'youtu.be', 'gmail.com', 'goo.gl', 'googleusercontent.com', 'blogspot.com',
            'facebook.com', 'fb.com', 'instagram.com', 'whatsapp.com', 'wa.me', 'messenger.com', 'threads.net',
            'twitter.com', 'x.com', 't.co', 'tiktok.com', 'linkedin.com', 'pinterest.com', 'reddit.com', 'tumblr.com', 'quora.com',
            'telegram.org', 't.me', 'telegram.me', 'discord.com', 'discord.gg', 'twitch.tv', 'snapchat.com', 'line.me', 'kakao.com', 'vk.com',
            'github.com', 'gitlab.com', 'bitbucket.org', 'stackoverflow.com', 'npmjs.com', 'pypi.org', 'wikipedia.org', 'wikimedia.org', 'archive.org',
            'microsoft.com', 'live.com', 'outlook.com', 'office.com', 'apple.com', 'icloud.com', 'mozilla.org', 'cloudflare.com',
            'amazon.com', 'amazon.co.jp', 'amazon.de', 'amazon.co.uk', 'ebay.com', 'etsy.com', 'aliexpress.com', 'shopee.co.id', 'tokopedia.com',
            'bukalapak.com', 'lazada.co.id', 'blibli.com', 'mercadolivre.com.br', 'rakuten.co.jp', 'mercari.com',
            'paypal.com', 'stripe.com', 'netflix.com', 'spotify.com', 'soundcloud.com', 'vimeo.com', 'imdb.com', 'medium.com', 'substack.com',
            'wordpress.com', 'notion.so', 'canva.com', 'figma.com', 'dropbox.com', 'zoom.us', 'slack.com', 'trello.com', 'atlassian.net',
            'yahoo.com', 'bing.com', 'duckduckgo.com', 'baidu.com', 'naver.com', 'yandex.ru', 'mail.ru',
            'detik.com', 'kompas.com', 'tribunnews.com', 'cnnindonesia.com', 'liputan6.com', 'tempo.co', 'kumparan.com',
            'nytimes.com', 'bbc.co.uk', 'bbc.com', 'theguardian.com', 'cnn.com', 'reuters.com', 'apnews.com', 'lemonde.fr', 'spiegel.de',
            'zeit.de', 'asahi.com', 'nikkei.com', 'globo.com', 'uol.com.br', 'elpais.com', 'marca.com', 'rt.com', 'ria.ru',
            'gojek.com', 'grab.com', 'traveloka.com', 'tiket.com', 'booking.com', 'airbnb.com', 'agoda.com',
        ], explode(',', (string) env('GUEST_ALLOWLIST_EXTRA', ''))))))),
    ],

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
        // Words the family's other apps and tools use as a path or product name.
        'qr', 'wifi', 'vcard', 'pay', 'split', 'send', 'secret', 'embargo',
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

    /*
    |--------------------------------------------------------------------------
    | Private destinations
    |--------------------------------------------------------------------------
    |
    | Off: a destination on a private address (loopback, private and link-local
    | ranges, localhost, *.local and the like) is refused. Turn it on only for an
    | install used inside its own network. A login in the address stays refused,
    | and the server's own requests (destination monitor, QR logo, webhooks) still
    | never go to a private address.
    |
    */
    'allow_private_destinations' => (bool) env('LINK_ALLOW_PRIVATE_DESTINATIONS', false),

    /*
    |--------------------------------------------------------------------------
    | Destination monitor
    |--------------------------------------------------------------------------
    |
    | links:check-destinations (hourly) asks the destination of live links, least
    | recently checked first, at most `batch` links per run. A link is checked
    | again after `interval_hours`, or after `retry_hours` while its last check
    | failed. After `failures` failed checks in a row it counts as broken and the
    | owner gets one email until the destination answers again.
    |
    */
    'monitor' => [
        'enabled' => (bool) env('LINK_MONITOR_ENABLED', true),
        'batch' => max(1, (int) env('LINK_MONITOR_BATCH', 100)),
        'interval_hours' => 24,
        'retry_hours' => 6,
        'failures' => 3,
        'timeout' => 8,
        'max_redirects' => 3,
    ],
];
