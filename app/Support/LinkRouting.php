<?php

namespace App\Support;

use App\Models\Link;
use App\Services\GeoIpService;
use App\Services\UserAgentService;
use Hop\Safety\LinkSafety;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

/**
 * Where a visit goes when the link has more than one answer: routing rules (country, language, device, system,
 * browser; the first rule that matches wins) and the A/B split. Shared by the dashboard form, the API and the redirect,
 * like LinkLimits. Rules take precedence over the split; crawlers and link-preview bots always get the link's own address.
 */
class LinkRouting
{
    public const MAX_RULES = 20;

    public const DEVICES = ['mobile', 'tablet', 'desktop'];

    public const SYSTEMS = ['ios', 'android', 'windows', 'macos', 'linux', 'chromeos'];

    public const BROWSERS = ['chrome', 'safari', 'firefox', 'edge', 'opera', 'samsung'];

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'routing_rules' => ['nullable', 'array', 'max:'.self::MAX_RULES],
            'routing_rules.*' => ['array'],
            'routing_rules.*.url' => ['nullable', 'string', 'max:2000'],
            // Listed so validated() keeps them; the values themselves are checked in problems().
            'routing_rules.*.country' => ['nullable'],
            'routing_rules.*.language' => ['nullable'],
            'routing_rules.*.device' => ['nullable'],
            'routing_rules.*.os' => ['nullable'],
            'routing_rules.*.browser' => ['nullable'],
            'ab_variants' => ['nullable', 'array', 'max:'.AbSplit::MAX],
            'ab_variants.*' => ['array'],
            'ab_variants.*.url' => ['nullable', 'string', 'max:2000'],
            'ab_variants.*.weight' => ['nullable'],
        ];
    }

    /**
     * Tidy the two fields in a request's input before validation; keys that were not sent stay absent.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function tidy(array $input): array
    {
        $out = [];
        if (array_key_exists('routing_rules', $input)) {
            $out['routing_rules'] = self::tidyRules($input['routing_rules']);
        }
        if (array_key_exists('ab_variants', $input)) {
            $out['ab_variants'] = AbSplit::tidy($input['ab_variants']);
        }

        return $out;
    }

    /** The two link columns as they are saved. @return array{routing_rules: ?array, ab_variants: ?array} */
    public static function columns(array $data): array
    {
        $rules = self::tidyRules($data['routing_rules'] ?? []);

        return [
            'routing_rules' => is_array($rules) && $rules !== [] ? array_values($rules) : null,
            'ab_variants' => AbSplit::store($data['ab_variants'] ?? []),
        ];
    }

    /** Add the problems the plain rules cannot say (conditions, safety, loops) to a validator. */
    public static function check(Validator $validator, array $input): void
    {
        $rules = $input['routing_rules'] ?? [];
        $variants = $input['ab_variants'] ?? [];
        if (is_array($rules) && ! $validator->errors()->has('routing_rules')) {
            foreach (self::problems($rules) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }
        if (is_array($variants) && ! $validator->errors()->has('ab_variants')) {
            foreach (AbSplit::problems(AbSplit::tidy($variants)) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }
    }

    /** @return array<string, string> */
    public static function problems(array $rules): array
    {
        $problems = [];
        foreach (self::tidyRules($rules) as $i => $rule) {
            $n = $i + 1;
            if (! is_array($rule)) {
                $problems["routing_rules.$i"] = __('Rule :n is not valid.', ['n' => $n]);

                continue;
            }
            foreach ($rule['country'] as $code) {
                if (! preg_match('/^[A-Z]{2}$/', $code)) {
                    $problems["routing_rules.$i.country"] = __('Rule :n: ":code" is not a two-letter country code (for example DE, ID, US).', ['n' => $n, 'code' => $code]);
                }
            }
            foreach ($rule['language'] as $code) {
                if (! preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $code)) {
                    $problems["routing_rules.$i.language"] = __('Rule :n: ":code" is not a language code (for example de, pt-BR).', ['n' => $n, 'code' => $code]);
                }
            }
            foreach ([['device', self::DEVICES], ['os', self::SYSTEMS], ['browser', self::BROWSERS]] as [$key, $allowed]) {
                if ($rule[$key] !== 'any' && ! in_array($rule[$key], $allowed, true)) {
                    $problems["routing_rules.$i.$key"] = __('Rule :n: pick one of the listed values.', ['n' => $n]);
                }
            }
            if ($rule['country'] === [] && $rule['language'] === [] && $rule['device'] === 'any' && $rule['os'] === 'any' && $rule['browser'] === 'any') {
                $problems["routing_rules.$i"] = __('Rule :n needs at least one condition: a country, language, device, system or browser.', ['n' => $n]);
            }
            if ($problem = self::addressProblem($rule['url'])) {
                $problems["routing_rules.$i.url"] = __('Rule :n: :problem', ['n' => $n, 'problem' => __($problem)]);
            }
        }

        return $problems;
    }

    /** Why an address cannot be a destination, or null when it is fine. It has to pass the same safety checks as the main address. */
    public static function addressProblem(string $url): ?string
    {
        if (trim($url) === '') {
            return 'Add the address visitors should go to.';
        }
        if (! preg_match('#^https?://#i', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'Invalid URL format. Please check the URL format.';
        }
        if (LinkLimits::pointsAtShortLinks($url)) {
            return 'The address cannot be another short link: it could send visitors in a circle.';
        }

        return app(LinkSafety::class)->rejectionReason($url);
    }

    /** A destination as people type it: trimmed, https:// added when there is no scheme. */
    public static function address(mixed $url): string
    {
        $url = is_scalar($url) ? trim((string) $url) : '';

        // Only a bare address gets https://; anything that already names a scheme (ftp:, javascript:) is left for the checks to refuse.
        $hasScheme = preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) && ! preg_match('#^[^/:]+:\d+(/|$)#', $url);

        return $url !== '' && ! $hasScheme ? 'https://'.$url : $url;
    }

    /**
     * The address and the split position (1-based, or null) this visit gets.
     *
     * @return array{url: string, variant: ?int}
     */
    public static function resolve(Link $link, Request $request): array
    {
        $default = ['url' => $link->target_url, 'variant' => null];
        $rules = (array) $link->routing_rules;
        $variants = AbSplit::variants($link);
        if ($rules === [] && $variants === []) {
            return $default;
        }
        $ua = app(UserAgentService::class)->parse($request->userAgent());
        if ($ua['bot']) {
            return $default;
        }
        if ($url = self::match($rules, $ua, $request->header('Accept-Language'), fn () => self::countryOf((string) $request->ip()))) {
            return ['url' => $url, 'variant' => null];
        }
        if ($variants) {
            $i = AbSplit::pick($variants, $link->id, VisitorHash::make($request->ip()));

            return ['url' => $variants[$i]['url'], 'variant' => $i + 1];
        }

        return $default;
    }

    /**
     * The first rule whose conditions all hold decides the address. The country is looked up only when a rule with a
     * country is reached and the cheaper conditions of that rule already hold.
     *
     * @param  array<int, mixed>  $rules
     * @param  array{device?: string, browser?: string, os?: string}  $ua
     * @param  callable(): ?string  $country
     */
    public static function match(array $rules, array $ua, ?string $acceptLanguage, callable $country): ?string
    {
        $device = strtolower((string) ($ua['device'] ?? ''));
        $system = self::systemOf((string) ($ua['os'] ?? ''));
        $browser = self::browserOf((string) ($ua['browser'] ?? ''));
        $languages = null;
        $code = null;
        $looked = false;
        foreach ($rules as $rule) {
            if (! is_array($rule) || ! preg_match('#^https?://#i', (string) ($rule['url'] ?? ''))) {
                continue;
            }
            if (($rule['device'] ?? 'any') !== 'any' && $rule['device'] !== $device) {
                continue;
            }
            if (($rule['os'] ?? 'any') !== 'any' && $rule['os'] !== $system) {
                continue;
            }
            if (($rule['browser'] ?? 'any') !== 'any' && $rule['browser'] !== $browser) {
                continue;
            }
            if (! empty($rule['language'])) {
                $languages ??= self::languages($acceptLanguage);
                if (! self::languageMatches((array) $rule['language'], $languages[0] ?? null)) {
                    continue;
                }
            }
            if (! empty($rule['country'])) {
                if (! $looked) {
                    $code = strtoupper((string) $country());
                    $looked = true;
                }
                if (! in_array($code, (array) $rule['country'], true)) {
                    continue;
                }
            }

            return $rule['url'];
        }

        return null;
    }

    /** Language tags of an Accept-Language header, lower case, the visitor's favourite first. @return array<int, string> */
    public static function languages(?string $header): array
    {
        $found = [];
        foreach (explode(',', (string) $header) as $i => $part) {
            [$tag, $params] = array_pad(explode(';', trim($part), 2), 2, '');
            $tag = strtolower(trim($tag));
            if (! preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/', $tag)) {
                continue;
            }
            $q = preg_match('/q\s*=\s*([0-9.]+)/i', $params, $m) ? (float) $m[1] : 1.0;
            if ($q > 0) {
                $found[] = [$tag, $q, $i];
            }
        }
        usort($found, fn ($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

        return array_column($found, 0);
    }

    /** "de" matches de, de-AT and de-CH; "pt-br" matches only pt-BR. */
    private static function languageMatches(array $wanted, ?string $visitor): bool
    {
        if ($visitor === null) {
            return false;
        }
        foreach ($wanted as $code) {
            if ($visitor === $code || (! str_contains($code, '-') && explode('-', $visitor)[0] === $code)) {
                return true;
            }
        }

        return false;
    }

    /** The operating-system name device-detector gives, as one of SYSTEMS (null when it is something else). */
    public static function systemOf(string $os): ?string
    {
        return match (true) {
            (bool) preg_match('/^(ios|ipados)/i', $os) => 'ios',
            stripos($os, 'android') !== false => 'android',
            stripos($os, 'windows') === 0 => 'windows',
            stripos($os, 'chrome os') !== false => 'chromeos',
            $os === 'Mac' || stripos($os, 'macos') === 0 => 'macos',
            (bool) preg_match('/linux|ubuntu|debian|fedora|arch|mint|suse|centos|red hat|gentoo/i', $os) => 'linux',
            default => null,
        };
    }

    /** The browser name device-detector gives, as one of BROWSERS (null when it is something else). */
    public static function browserOf(string $name): ?string
    {
        $name = strtolower($name);

        return match (true) {
            str_contains($name, 'samsung') => 'samsung',
            str_contains($name, 'edge') => 'edge',
            str_contains($name, 'opera') => 'opera',
            str_contains($name, 'firefox') => 'firefox',
            str_contains($name, 'chrome') => 'chrome',
            str_contains($name, 'safari') => 'safari',
            default => null,
        };
    }

    private static function countryOf(string $ip): ?string
    {
        try {
            return $ip === '' ? null : app(GeoIpService::class)->country($ip);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int, mixed> */
    private static function tidyRules(mixed $rules): mixed
    {
        if (! is_array($rules)) {
            return blank($rules) ? [] : $rules;
        }
        $out = [];
        foreach ($rules as $row) {
            if (! is_array($row)) {
                $out[] = $row;

                continue;
            }
            $rule = [
                'country' => self::tokens($row['country'] ?? '', 'strtoupper'),
                'language' => self::tokens($row['language'] ?? '', 'strtolower'),
                'device' => self::choice($row['device'] ?? ''),
                'os' => self::choice($row['os'] ?? ''),
                'browser' => self::choice($row['browser'] ?? ''),
                'url' => self::address($row['url'] ?? ''),
            ];
            // A row nobody filled in (the form keeps an empty one around) is not a rule.
            if ($rule['url'] === '' && $rule['country'] === [] && $rule['language'] === [] && $rule['device'] === 'any' && $rule['os'] === 'any' && $rule['browser'] === 'any') {
                continue;
            }
            $out[] = $rule;
        }

        return $out;
    }

    /** @return array<int, string> */
    private static function tokens(mixed $value, callable $case): array
    {
        $parts = is_array($value) ? $value : preg_split('/[\s,;]+/', (string) $value);

        return array_values(array_unique(array_filter(array_map(fn ($p) => is_scalar($p) ? $case(trim((string) $p)) : '', $parts), fn ($p) => $p !== '')));
    }

    private static function choice(mixed $value): string
    {
        $value = is_scalar($value) ? strtolower(trim((string) $value)) : 'any';

        return $value === '' ? 'any' : $value;
    }
}
