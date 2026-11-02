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
if (! function_exists('asset_host_url')) {
    /**
     * An address for a file the app host serves (ASSET_URL, default APP_URL: /storage, /images, /brand, /build) that
     * works on any page, whichever host rendered it. A page at hel.ink/b/ or hel.ink/l/ would otherwise ask hel.ink for
     * "/storage/x.png" and get a 404. Absolute addresses (http, https, //host, data:, blob:) are returned unchanged.
     * asset_host_url('/storage/a.png') → https://link.hel.ink/storage/a.png.
     */
    function asset_host_url(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '' || preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $path)) {
            return $path;
        }
        $base = rtrim((string) (config('app.asset_url') ?: config('app.url')), '/');

        return $base.'/'.ltrim($path, '/');
    }
}
if (! function_exists('hub_asset_url')) {
    /**
     * A file the hub holds (profile photos live in the hub's public disk) as an address that works from this app.
     * Absolute addresses (the hub's own, Google's) are returned unchanged; a bare path ("avatars/a.png",
     * "/storage/avatars/a.png") is put on the hub host. hub_asset_url('avatars/a.png') → https://hub.hel.ink/storage/avatars/a.png.
     */
    function hub_asset_url(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '' || preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $path)) {
            return $path;
        }
        $path = preg_replace('#^/?(storage/)?#', '', $path);

        return hub_url('storage/'.$path);
    }
}
if (! function_exists('bio_enabled')) {
    /** Whether the hop/bio package is switched on (BIO_ENABLED). */
    function bio_enabled(): bool
    {
        return (bool) config('bio.enabled', false);
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

        // No flag file for this country: the code itself, never a picture from another host.
        return '<span class="inline-block rounded-sm bg-slate-200 px-1 text-[10px] font-semibold leading-4 text-slate-600 dark:bg-slate-700 dark:text-slate-200" title="'.e($countryName).'">'.e(strtoupper($code)).'</span>';
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
