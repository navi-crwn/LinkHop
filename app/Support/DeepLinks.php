<?php

namespace App\Support;

use App\Models\Link;
use Hop\Safety\LinkSafety;
use Illuminate\Validation\Validator;

/**
 * "Open in the app": a short link that tries the installed app of the destination on iOS and Android, and falls back
 * to the page (or an app store) when it is not there. Shared by the dashboard form, the API and the redirect, like LinkRouting.
 *
 * `links.app_links` is null (off) or {mode: auto|custom, ios, android, ios_store, android_store}:
 *  - auto:   the destination is recognised (YouTube, Spotify, WhatsApp, Telegram, Instagram, X, TikTok, Zoom, App Store, Google Play)
 *            and the app address is built from it by hop/safety's DeepLinks (https destinations only);
 *  - custom: the owner types the app address for iOS and/or Android (a URL scheme such as `myapp://item/1`, an https
 *            universal link, or an Android `intent://...#Intent;...;end` address) and optional store pages.
 * Crawlers, desktops and visitors on other systems keep the plain redirect.
 */
class DeepLinks
{
    public const MODES = ['auto', 'custom'];

    /** Schemes that can never be an app address: they dial, write, read files, run script or hand over data. */
    private const BLOCKED_SCHEMES = ['javascript', 'data', 'vbscript', 'file', 'blob', 'about', 'ftp', 'tel', 'sms', 'smsto', 'mms', 'mailto', 'content', 'jar', 'view-source', 'ws', 'wss', 'chrome', 'chrome-extension', 'resource', 'http', 'sip', 'facetime', 'facetime-audio'];

    /** Pages where an app can be bought, so a store address has to be one of these. */
    private const STORE_HOSTS = ['apps.apple.com', 'itunes.apple.com', 'play.google.com'];

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'app_links' => ['nullable', 'array'],
            'app_links.mode' => ['nullable', 'string', 'in:off,'.implode(',', self::MODES)],
            'app_links.ios' => ['nullable', 'string', 'max:2000'],
            'app_links.android' => ['nullable', 'string', 'max:2000'],
            'app_links.ios_store' => ['nullable', 'string', 'max:2000'],
            'app_links.android_store' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Tidy the field in a request's input before validation; it stays absent when it was not sent.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function tidy(array $input): array
    {
        if (! array_key_exists('app_links', $input)) {
            return [];
        }
        $raw = is_array($input['app_links']) ? $input['app_links'] : [];
        $clean = ['mode' => strtolower(trim((string) ($raw['mode'] ?? 'off')))];
        foreach (['ios', 'android', 'ios_store', 'android_store'] as $key) {
            $clean[$key] = self::text($raw[$key] ?? '');
        }

        return ['app_links' => $clean];
    }

    /** The column as it is saved: null when it is off. @return array{app_links: ?array} */
    public static function columns(array $data): array
    {
        $links = is_array($data['app_links'] ?? null) ? $data['app_links'] : [];
        $mode = strtolower(trim((string) ($links['mode'] ?? 'off')));
        if (! in_array($mode, self::MODES, true)) {
            return ['app_links' => null];
        }
        $out = ['mode' => $mode];
        foreach (['ios', 'android', 'ios_store', 'android_store'] as $key) {
            $value = self::text($links[$key] ?? '');
            if ($mode === 'custom' && $value !== '') {
                $out[$key] = $value;
            } elseif ($mode === 'auto' && in_array($key, ['ios_store', 'android_store'], true) && $value !== '') {
                $out[$key] = $value;
            }
        }

        return ['app_links' => $out];
    }

    /** Add the problems the plain rules cannot say (schemes, safety) to a validator. */
    public static function check(Validator $validator, array $input): void
    {
        $links = $input['app_links'] ?? null;
        if (! is_array($links) || $validator->errors()->has('app_links')) {
            return;
        }
        foreach (self::problems($links) as $field => $message) {
            $validator->errors()->add($field, $message);
        }
    }

    /** @return array<string, string> */
    public static function problems(array $links): array
    {
        $links = self::tidy(['app_links' => $links])['app_links'];
        $problems = [];
        $mode = $links['mode'];
        if ($mode === 'custom' && $links['ios'] === '' && $links['android'] === '') {
            $problems['app_links.ios'] = __('Add the app address for iOS or for Android.');
        }
        if ($mode === 'custom') {
            foreach (['ios', 'android'] as $os) {
                if ($links[$os] !== '' && ($problem = self::appAddressProblem($links[$os], $os))) {
                    $problems["app_links.$os"] = __($problem);
                }
            }
        }
        if (in_array($mode, self::MODES, true)) {
            foreach (['ios_store', 'android_store'] as $key) {
                if ($links[$key] !== '' && ($problem = self::storeProblem($links[$key]))) {
                    $problems["app_links.$key"] = __($problem);
                }
            }
        }

        return $problems;
    }

    /** Why an address cannot open an app, or null when it is fine. */
    public static function appAddressProblem(string $url, string $os): ?string
    {
        if (preg_match('/[\s\x00-\x1f\x7f]/', $url)) {
            return 'The app address cannot contain spaces.';
        }
        if (! preg_match('#^([a-z][a-z0-9+.\-]{1,31}):#i', $url, $m)) {
            return 'Start with the app scheme (for example myapp://item/1) or with https://.';
        }
        $scheme = strtolower($m[1]);
        if (in_array($scheme, self::BLOCKED_SCHEMES, true)) {
            return 'This kind of address cannot be used to open an app.';
        }
        if ($scheme === 'intent') {
            return $os === 'android' ? self::intentProblem($url) : 'Intent addresses work on Android only.';
        }
        if ($scheme === 'https') {
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                return 'Invalid URL format. Please check the URL format.';
            }
            if (LinkLimits::pointsAtShortLinks($url)) {
                return 'The address cannot be another short link: it could send visitors in a circle.';
            }

            return app(LinkSafety::class)->rejectionReason($url);
        }

        return null;
    }

    /** An Android intent address has to name a package and may not reach into components or other intents. */
    private static function intentProblem(string $url): ?string
    {
        if (! preg_match('/^intent:\/\/[^#]*#Intent;.*;end$/s', $url)) {
            return 'An Android intent address looks like intent://host/path#Intent;scheme=https;package=com.example.app;end';
        }
        if (! preg_match('/;package=[a-z][a-z0-9_]*(\.[a-z0-9_]+)+;/i', $url)) {
            return 'An Android intent address has to name the app with package=.';
        }
        if (preg_match('/;(component|SEL|launchFlags|action)=/i', $url)) {
            return 'This kind of address cannot be used to open an app.';
        }
        if (preg_match('/S\.browser_fallback_url=([^;]*)/', $url, $m) && ! preg_match('#^https%3A%2F%2F#i', $m[1])) {
            return 'The fallback address inside an intent has to be an https address.';
        }
        // the fallback is opened in a browser, so it follows the same rule as any destination (an encrypted note's key is refused)
        $notes = app(EncryptedNoteLink::class);

        return $notes->matches($url) ? $notes->message() : null;
    }

    /** A store page: App Store, Google Play, or their app schemes. */
    public static function storeProblem(string $url): ?string
    {
        if (preg_match('#^(itms-apps|itms-appss|market)://#i', $url)) {
            return preg_match('/[\s\x00-\x1f\x7f]/', $url) ? 'The app address cannot contain spaces.' : null;
        }
        if (! preg_match('#^https://#i', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'Use the page of the app in the App Store or Google Play.';
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! in_array($host, self::STORE_HOSTS, true)) {
            return 'Use the page of the app in the App Store or Google Play.';
        }

        return null;
    }

    /**
     * The app address for this visitor, or null when the visit stays a plain redirect.
     *
     * @param  array{os?: string, bot?: bool, in_app?: ?string}  $ua
     * @return array{os: string, app: string, fallback: string, app_name: ?string}|null
     */
    public static function resolve(Link $link, string $webUrl, array $ua, bool $routed = false): ?array
    {
        $config = $link->app_links;
        if (! is_array($config) || ! in_array($config['mode'] ?? null, self::MODES, true) || ! empty($ua['bot'])) {
            return null;
        }
        $os = LinkRouting::systemOf((string) ($ua['os'] ?? ''));
        if (! in_array($os, ['ios', 'android'], true)) {
            return null;
        }
        // The known apps (YouTube, Spotify, WhatsApp, ...) come from the shared library: https destinations only, every piece
        // of the app address encoded, the host matched as a whole name. What the library does not know stays a plain redirect.
        $deep = app(\Hop\Safety\DeepLinks::class)->resolve($webUrl);
        $auto = $deep ? ['name' => $deep->name, 'ios' => $deep->ios, 'android' => $deep->android] : null;
        $name = $auto['name'] ?? null;
        if (($config['mode'] ?? '') === 'custom') {
            // A rule or a split chose another address than the link's own: the typed app address no longer matches it.
            $app = $routed ? ($auto[$os] ?? null) : ($config[$os] ?? null);
        } else {
            $app = $auto[$os] ?? null;
        }
        if (! is_string($app) || $app === '') {
            return null;
        }
        $store = $config[$os.'_store'] ?? null;

        return ['os' => $os, 'app' => $app, 'fallback' => is_string($store) && $store !== '' ? $store : $webUrl, 'app_name' => $name];
    }

    /** A short sentence for the form: what the current destination is, or null when it is not recognised. */
    public static function recognised(?string $url): ?string
    {
        return $url ? app(\Hop\Safety\DeepLinks::class)->recognise($url) : null;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
