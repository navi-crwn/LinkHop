<?php

use Illuminate\Support\Facades\DB;

if (! function_exists('public_url')) {
    /**
     * A URL on the public host (PUBLIC_URL, default APP_URL): short links, bio
     * pages, QR codes, og:url, the sitemap. public_url('l/abc') → https://hel.ink/l/abc.
     */
    function public_url(string $path = ''): string
    {
        $base = (string) config('shortener.public_url');
        $path = ltrim($path, '/');

        return $path === '' ? $base : $base.'/'.$path;
    }
}
if (! function_exists('public_host')) {
    /** The public host without www., for display next to slug inputs (hel.ink). */
    function public_host(): string
    {
        return preg_replace('/^www\./', '', (string) parse_url(public_url(), PHP_URL_HOST));
    }
}
if (! function_exists('hub_url')) {
    /** A URL on the hub (HOP ID, hub.hel.ink): hub_url('settings') → https://hub.hel.ink/settings. */
    function hub_url(string $path = ''): string
    {
        $base = (string) config('hop.hub_url');
        $path = ltrim($path, '/');

        return $path === '' ? $base : $base.'/'.$path;
    }
}
if (! function_exists('bio_enabled')) {
    /** Whether the hop/bio package is switched on (BIO_ENABLED). */
    function bio_enabled(): bool
    {
        return (bool) config('bio.enabled', false);
    }
}
if (! function_exists('simple_icon_url')) {
    /** Get Simple Icons CDN URL for a platform/brand */
    function simple_icon_url(string $platform, ?string $color = null): ?string
    {
        // Platform to Simple Icons slug mapping
        $mapping = [
            'amazon' => 'amazon',
            'amazon-music' => 'amazonmusic',
            'apple' => 'apple',
            'apple-music' => 'applemusic',
            'apple-music-alt' => 'applemusic',
            'apple-podcasts' => 'applepodcasts',
            'apple-podcasts-alt' => 'applepodcasts',
            'artstation' => 'artstation',
            'audiomack' => 'audiomack',
            'bandcamp' => 'bandcamp',
            'behance' => 'behance',
            'bereal' => 'bereal',
            'bluesky' => 'bluesky',
            'bluesky-alt' => 'bluesky',
            'buy-me-a-coffee' => 'buymeacoffee',
            'cal' => 'caldotcom',
            'calendly' => 'calendly',
            'cameo' => 'cameo',
            'cash-app' => 'cashapp',
            'cash-app-btc' => 'cashapp',
            'cash-app-dollar' => 'cashapp',
            'cash-app-pound' => 'cashapp',
            'clubhouse' => 'clubhouse',
            'codeberg' => 'codeberg',
            'codepen' => 'codepen',
            'deezer' => 'deezer',
            'dev-to' => 'devdotto',
            'discord' => 'discord',
            'discogs' => 'discogs',
            'discogs-alt' => 'discogs',
            'dribbble' => 'dribbble',
            'email' => 'gmail',
            'email-alt' => 'maildotru',
            'etsy' => 'etsy',
            'facebook' => 'facebook',
            'figma' => 'figma',
            'fiverr' => 'fiverr',
            'flickr' => 'flickr',
            'github' => 'github',
            'gitlab' => 'gitlab',
            'gofundme' => 'gofundme',
            'gog' => 'gogdotcom',
            'goodreads' => 'goodreads',
            'google-drive' => 'googledrive',
            'google-play' => 'googleplay',
            'google-podcasts' => 'googlepodcasts',
            'google-scholar' => 'googlescholar',
            'guilded' => 'guilded',
            'gumroad' => 'gumroad',
            'hackerrank' => 'hackerrank',
            'hashnode' => 'hashnode',
            'instagram' => 'instagram',
            'itch-io' => 'itchdotio',
            'keybase' => 'keybase',
            'kick' => 'kick',
            'kick-alt' => 'kick',
            'kickstarter' => 'kickstarter',
            'kit' => 'convertkit',
            'ko-fi' => 'kofi',
            'last-fm' => 'lastdotfm',
            'leetcode' => 'leetcode',
            'lemmy' => 'lemmy',
            'letterboxd' => 'letterboxd',
            'line' => 'line',
            'linkedin' => 'linkedin',
            'mailchimp' => 'mailchimp',
            'mastodon' => 'mastodon',
            'matrix' => 'matrix',
            'medium' => 'medium',
            'meetup' => 'meetup',
            'meetup-alt' => 'meetup',
            'messenger' => 'messenger',
            'microsoft' => 'microsoft',
            'mixcloud' => 'mixcloud',
            'myanimelist' => 'myanimelist',
            'nostr' => 'nostr',
            'notion' => 'notion',
            'obsidian' => 'obsidian',
            'onlyfans' => 'onlyfans',
            'opensea' => 'opensea',
            'orcid' => 'orcid',
            'osu' => 'osu',
            'patreon' => 'patreon',
            'paypal' => 'paypal',
            'pinterest' => 'pinterest',
            'pixelfed' => 'pixelfed',
            'playstation' => 'playstation',
            'product-hunt' => 'producthunt',
            'qobuz' => 'qobuz',
            'redbubble' => 'redbubble',
            'reddit' => 'reddit',
            'researchgate' => 'researchgate',
            'revolut' => 'revolut',
            'signal' => 'signal',
            'signal-alt' => 'signal',
            'simplex' => 'simplex',
            'slack' => 'slack',
            'snapchat' => 'snapchat',
            'soundcloud' => 'soundcloud',
            'spotify' => 'spotify',
            'spotify-alt' => 'spotify',
            'stack-overflow' => 'stackoverflow',
            'steam' => 'steam',
            'strava' => 'strava',
            'substack' => 'substack',
            'telegram' => 'telegram',
            'threads' => 'threads',
            'threema' => 'threema',
            'tidal' => 'tidal',
            'tiktok' => 'tiktok',
            'trakt' => 'trakt',
            'trello' => 'trello',
            'tumblr' => 'tumblr',
            'twitch' => 'twitch',
            'twitter' => 'twitter',
            'unsplash' => 'unsplash',
            'upwork' => 'upwork',
            'venmo' => 'venmo',
            'vimeo' => 'vimeo',
            'vrchat' => 'vrchat',
            'vsco' => 'vsco',
            'whatsapp' => 'whatsapp',
            'wordpress' => 'wordpress',
            'x' => 'x',
            'xbox' => 'xbox',
            'xing' => 'xing',
            'youtube' => 'youtube',
            'youtube-alt' => 'youtube',
            'youtube-music' => 'youtubemusic',
            'zoom' => 'zoom',
            // Generic icons that may not have Simple Icons equivalents
            'link' => null,
            'blog' => null,
            'website' => null,
            'generic-website' => null,
            'generic-email' => 'gmail',
            'generic-email-alt' => 'maildotru',
            'generic-calendar' => null,
            'generic-phone' => null,
            'generic-sms' => null,
            'generic-shopping-bag' => null,
            'generic-shopping-tag' => null,
        ];
        $iconName = $mapping[$platform] ?? null;
        if (! $iconName) {
            return null;
        }
        $url = "https://cdn.simpleicons.org/{$iconName}";
        if ($color) {
            // Remove # if present
            $color = ltrim($color, '#');
            $url .= "/{$color}";
        }

        return $url;
    }
}
if (! function_exists('country_flag')) {
    function country_flag(?string $countryCode): string
    {
        if (! $countryCode || strlen($countryCode) !== 2) {
            return '<span class="inline-block w-5 h-4" title="Unknown">🌍</span>';
        }
        $code = strtolower($countryCode);
        $countryName = country_name(strtoupper($countryCode));
        $localPath = public_path("images/flags/{$code}.svg");
        if (file_exists($localPath)) {
            $url = asset("images/flags/{$code}.svg");

            return '<img src="'.$url.'" 
                         width="20" 
                         height="15"
                         alt="'.$countryName.'" 
                         title="'.$countryName.'"
                         class="inline-block rounded-sm"
                         style="border-radius: 2px;">';
        }

        return '<img src="https://flagcdn.com/w20/'.$code.'.png" 
                     srcset="https://flagcdn.com/w40/'.$code.'.png 2x" 
                     width="20" 
                     alt="'.$countryName.'" 
                     title="'.$countryName.'"
                     class="inline-block rounded-sm">';
    }
}
if (! function_exists('country_name')) {
    function country_name(?string $countryCode): string
    {
        if (! $countryCode) {
            return 'Unknown';
        }
        $countries = [
            'US' => 'United States',
            'ID' => 'Indonesia',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'RU' => 'Russia',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'TH' => 'Thailand',
            'PH' => 'Philippines',
            'VN' => 'Vietnam',
            'NL' => 'Netherlands',
            'IT' => 'Italy',
            'ES' => 'Spain',
        ];

        return $countries[strtoupper($countryCode)] ?? $countryCode;
    }
}

if (! function_exists('sql_date_format')) {
    /**
     * Portable DATE_FORMAT(column, format) for MySQL/MariaDB and SQLite.
     * Supports the specifiers used in this app: %Y %m %d %H %i %s %u.
     */
    function sql_date_format(string $column, string $format): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            // MySQL %u (week, Monday first) is %W in SQLite; %i (minutes) is %M.
            $format = strtr($format, ['%u' => '%W', '%i' => '%M']);

            return "strftime('".str_replace("'", "''", $format)."', {$column})";
        }

        return "DATE_FORMAT({$column}, '".str_replace("'", "''", $format)."')";
    }
}

if (! function_exists('csv_safe')) {
    /**
     * Prefix values that spreadsheets would evaluate as formulas (CSV injection).
     */
    function csv_safe(array $row): array
    {
        return array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, $row);
    }
}
