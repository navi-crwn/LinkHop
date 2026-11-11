<?php

namespace App\Support;

use Illuminate\Validation\Validator;

/**
 * "App store link": one short link that sends each operating system to its own app store (Google Play on Android,
 * the App Store on iPhone and iPad, the Mac App Store on macOS, the Microsoft Store on Windows).
 *
 * It is a guided way to write routing rules (App\Support\LinkRouting): one rule per filled store, the condition being
 * that system, and a `store` marker on the rule so the edit page can show the rules back in the store form. The redirect
 * engine does not know about it. A rule only keeps its marker while it is exactly what the form would write (that
 * system and nothing else, an address of that store); a link whose rules were edited by hand into something else is
 * shown in the plain rules editor, nothing lost.
 *
 * Accepted addresses (https only, no port, no user info, the host matched as a whole name):
 *  - play:     play.google.com/store/apps/details?id=<package>, or another page under /store/apps/ (developer, collection)
 *  - appstore: apps.apple.com or itunes.apple.com, /[cc/]app/[name/]id<digits>
 *  - macstore: the same pages (an App Store page opens in the Mac App Store on a Mac)
 *  - msstore:  apps.microsoft.com/detail/<id> or /store/detail/<name>/<id>; www.microsoft.com (or microsoft.com)
 *              /[xx-xx/]store/apps/<id>, /store/productId/<id>, /[xx-xx/]p/<name>/<id>, /[xx-xx/]store/p/<name>/<id>
 * Every address also has to pass the destination checks any routing address passes (LinkRouting::addressProblem).
 */
class StoreLinks
{
    /** @var array<string, array{name: string, os: string, example: string}> in the order the rules are written */
    public const STORES = [
        'play' => ['name' => 'Google Play', 'os' => 'android', 'example' => 'https://play.google.com/store/apps/details?id=com.example.app'],
        'appstore' => ['name' => 'App Store', 'os' => 'ios', 'example' => 'https://apps.apple.com/app/id0000000000'],
        'macstore' => ['name' => 'Mac App Store', 'os' => 'macos', 'example' => 'https://apps.apple.com/app/id0000000000'],
        'msstore' => ['name' => 'Microsoft Store', 'os' => 'windows', 'example' => 'https://apps.microsoft.com/detail/9abcdef12345'],
    ];

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'store_mode' => ['nullable'],
            'store_links' => ['nullable', 'array'],
            'store_links.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Does this input ask for the store form? */
    public static function active(array $input): bool
    {
        return filter_var($input['store_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Tidy store_links (trimmed, https:// added to a bare address, empty ones dropped, unknown keys dropped) and, when
     * the store form is on, write routing_rules from it. Keys that were not sent stay absent.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function tidy(array $input): array
    {
        $out = [];
        if (array_key_exists('store_mode', $input)) {
            $out['store_mode'] = self::active($input);
        }
        if (array_key_exists('store_links', $input)) {
            $links = [];
            foreach (is_array($input['store_links']) ? $input['store_links'] : [] as $key => $url) {
                if (isset(self::STORES[$key]) && is_scalar($url) && trim((string) $url) !== '') {
                    $links[$key] = LinkRouting::address($url);
                }
            }
            $out['store_links'] = $links;
        }
        if (self::active($input)) {
            $out['routing_rules'] = self::rulesFrom($out['store_links'] ?? []);
        }

        return $out;
    }

    /** The routing rules for the filled stores, marked, in the order of STORES. @return array<int, array<string, mixed>> */
    public static function rulesFrom(array $links): array
    {
        $rules = [];
        foreach (self::STORES as $key => $store) {
            if (isset($links[$key]) && is_string($links[$key]) && $links[$key] !== '') {
                $rules[] = ['country' => '', 'language' => '', 'device' => 'any', 'os' => $store['os'], 'browser' => 'any', 'url' => $links[$key], 'store' => $key];
            }
        }

        return $rules;
    }

    /** True when this (tidied) rule is exactly what the store form writes, so its marker can stay. */
    public static function isStoreRule(array $rule, mixed $store): bool
    {
        return is_string($store) && isset(self::STORES[$store])
            && ($rule['os'] ?? null) === self::STORES[$store]['os']
            && ($rule['device'] ?? null) === 'any' && ($rule['browser'] ?? null) === 'any'
            && ($rule['country'] ?? []) === [] && ($rule['language'] ?? []) === []
            && self::matches($store, (string) ($rule['url'] ?? ''));
    }

    /**
     * The store form's values (store key => address) when every saved rule is a store rule, otherwise null: then the
     * link is shown in the plain rules editor. No rules at all is not a store link either.
     *
     * @return array<string, string>|null
     */
    public static function fromRules(mixed $rules): ?array
    {
        if (! is_array($rules) || $rules === []) {
            return null;
        }
        $out = [];
        foreach ($rules as $rule) {
            if (! is_array($rule) || isset($out[$rule['store'] ?? '']) || ! self::isStoreRule($rule, $rule['store'] ?? null)) {
                return null;
            }
            $out[$rule['store']] = (string) $rule['url'];
        }

        return $out;
    }

    /** Is this address a page of that store? */
    public static function matches(string $store, string $url): bool
    {
        if (! preg_match('#^https://([a-z0-9.\-]+)(/[^\s?\#]*)?(\?[^\s\#]*)?(\#\S*)?$#i', $url, $m)) {
            return false;
        }
        $host = strtolower($m[1]);
        $path = $m[2] ?? '';
        $query = $m[3] ?? '';
        switch ($store) {
            case 'play':
                if ($host !== 'play.google.com' || ! preg_match('#^/store/apps/[a-z]+#', $path)) {
                    return false;
                }
                if ($path === '/store/apps/details') {
                    parse_str(ltrim($query, '?'), $q);

                    return isset($q['id']) && is_string($q['id']) && preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/i', $q['id']) === 1;
                }

                return true;
            case 'appstore':
            case 'macstore':
                return in_array($host, ['apps.apple.com', 'itunes.apple.com'], true)
                    && preg_match('#^(/[a-z]{2})?/app/([^/]+/)?id\d{5,}/?$#i', $path) === 1;
            case 'msstore':
                if ($host === 'apps.microsoft.com') {
                    return preg_match('#^(/store)?/detail/[^/]+(/[A-Za-z0-9]+)?/?$#', $path) === 1;
                }

                return in_array($host, ['www.microsoft.com', 'microsoft.com'], true)
                    && preg_match('#^(/[a-z]{2}-[a-z]{2})?/(store/apps/[^/]+|store/productId/[A-Za-z0-9]+|(store/)?p/[^/]+/[A-Za-z0-9]+)/?$#i', $path) === 1;
        }

        return false;
    }

    /**
     * Problems with the (tidied) store addresses, keyed by field, in the words of the form. At least one address is
     * needed, each has to be a page of its own store and to pass the same checks any routing address passes.
     *
     * @return array<string, string>
     */
    public static function problems(array $links): array
    {
        if ($links === []) {
            return ['store_links' => __('Add at least one app store address.')];
        }
        $problems = [];
        foreach (self::STORES as $key => $store) {
            if (! isset($links[$key])) {
                continue;
            }
            if (! self::matches($key, $links[$key])) {
                $problems["store_links.$key"] = __('The :store address must be a page of your app in :store, for example :example.', ['store' => $store['name'], 'example' => $store['example']]);
            } elseif ($problem = LinkRouting::addressProblem($links[$key])) {
                $problems["store_links.$key"] = __(':store: :problem', ['store' => $store['name'], 'problem' => __($problem)]);
            }
        }

        return $problems;
    }

    /** Add the problems to a validator when the store form is on. */
    public static function check(Validator $validator, array $input): void
    {
        if (! self::active($input)) {
            return;
        }
        $links = is_array($input['store_links'] ?? null) ? $input['store_links'] : [];
        foreach (self::problems($links) as $field => $message) {
            if (! $validator->errors()->has($field)) {
                $validator->errors()->add($field, $message);
            }
        }
        if (filled($input['wa_mode'] ?? null)) {
            $validator->errors()->add('store_mode', __('Pick either the WhatsApp rotator or the app store link, not both.'));
        }
    }
}
