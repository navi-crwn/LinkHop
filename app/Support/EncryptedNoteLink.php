<?php

namespace App\Support;

use App\Models\HopApp;
use Hop\Safety\EncryptedNoteLink as AddressesInText;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Schema;

/**
 * Knows what an encrypted NoteHop link looks like, so LinkHop never stores one.
 *
 * NoteHop keeps the note key in the address fragment: https://n.hel.ink/TOKEN#k=KEY, and the owner link adds
 * &a=ADMIN. Browsers never send a fragment to a server, which is why NoteHop cannot read the note. A shortener
 * that stores the whole destination, and redirects to it, holds the key in its database. So a destination is
 * refused when its host is a NoteHop host and its fragment has a k or a parameter with a value (the two names
 * note.js reads from location.hash). Other hosts and other fragments are ordinary destinations.
 *
 * Registered as a scoped service, so the host list is built once per request or job.
 */
class EncryptedNoteLink
{
    /** The fragment parameters NoteHop treats as secrets: k is the note key, a the owner (admin) token. */
    public const SECRET_PARAMS = ['k', 'a'];

    /** The English text is also the key in lang/*.json. */
    public const MESSAGE = 'This link opens an encrypted NoteHop note, and the part after the # is its key. Shortening it would store that key on this server. Share the NoteHop link as it is (its share button already gives a short address), or use a Basic note, which the server can read anyway.';

    /** An Android intent address carries a fallback address (percent-encoded) that the browser opens when the app is missing. */
    private const INTENT_FALLBACK = '~^intent://[^#]*#Intent;(?:[^;]*;)*?S\.browser_fallback_url=([^;]*)~i';

    /** @var list<string>|null */
    private ?array $hosts = null;

    private ?AddressesInText $inText = null;

    /**
     * Lower-case NoteHop hosts: the HOP_NOTE_HOSTS list (default note.hel.ink and the short host n.hel.ink),
     * NOTEHOP_URL, NOTEHOP_SHARE_URL (a self-hosted install's second domain) and the registered NoteHop app.
     *
     * @return list<string>
     */
    public function hosts(): array
    {
        if ($this->hosts !== null) {
            return $this->hosts;
        }
        $hosts = (array) config('hop.note_hosts', []);
        $hosts[] = self::hostOf(config('services.notehop.url'));
        $hosts[] = self::hostOf(config('services.notehop.share_url'));
        try {
            if (Schema::hasTable('apps')) {
                $hosts[] = self::hostOf(HopApp::query()->where('slug', 'notehop')->value('url'));
            }
        } catch (\Throwable) {
            // no database yet (install, or a command that runs before migrations): the configured hosts still apply
        }

        return $this->hosts = array_values(array_unique(array_filter(array_map(self::hostOf(...), $hosts))));
    }

    /** True for a NoteHop address whose fragment carries the note key or the owner token. */
    public function matches(?string $url): bool
    {
        $clean = self::asBrowserReads((string) $url);
        // "intent://x#Intent;...;S.browser_fallback_url=https%3A%2F%2Fnote.hel.ink%2FID%23k%3DKEY;end" opens the note in a browser
        if (preg_match(self::INTENT_FALLBACK, $clean, $intent)) {
            return $this->matches(rawurldecode($intent[1]));
        }
        // A bare "host/path" or "//host/path" gets https:// from the forms, so it is read that way here too.
        if (! preg_match('~^[a-z][a-z0-9+.\-]*:~i', $clean) || preg_match('~^[^/:]+:\d+(/|$)~', $clean)) {
            $clean = 'https://'.ltrim($clean, '/\\');
        }
        // The forms put https:// in front of anything that does not start with "http://", so "https:/note.hel.ink/..."
        // can arrive as "https://https:/note.hel.ink/..." (the host is then "https", but the key is stored all the same).
        while (preg_match('~^https?:[/\\\\]*[\x00-\x20]*(?=https?:)~i', $clean)) {
            $clean = preg_replace('~^https?:[/\\\\]*[\x00-\x20]*~i', '', $clean);
        }
        // After "http:" or "https:" a browser skips every slash and backslash ("https:/host", "https:host" and
        // "https:////host" all name the host); the host ends at the next slash, backslash, ? or #.
        if (! preg_match('~^https?:[/\\\\]*([^/\\\\?#]*)~i', $clean, $m) || ($at = strpos($clean, '#')) === false) {
            return false;
        }
        $host = preg_replace('/:\d*$/', '', substr(strrchr('@'.$m[1], '@'), 1));

        return in_array(self::normalizeHost($host), $this->hosts(), true)
            && self::fragmentHasSecret(substr($clean, $at + 1));
    }

    /**
     * True when a value holds the address of an encrypted note anywhere in it: a text of any kind (rich text, CSS, a
     * label), or an array at any depth, its keys included. The text is also read the way a browser would read it, with
     * HTML entities and CSS escapes undone and tabs and line breaks dropped. Other values never hold one.
     */
    public function holdsKey(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $name => $item) {
                if ($this->holdsKey((string) $name) || $this->holdsKey($item)) {
                    return true;
                }
            }

            return false;
        }
        if (! is_string($value) || ! preg_match('/[#&\\\\]|%(25)*23/i', $value)) {
            return false;
        }
        if (($data = self::jsonArray($value)) !== null && $this->holdsKey($data)) {
            return true;
        }
        foreach (self::readings($value) as $reading) {
            if ($this->matches($reading) || $this->inText()->containsKey($reading)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add an error for each of the named fields that holds a key. For the fields that are no destination, which the
     * safety rule does not see: free text, image addresses, names.
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $fields
     */
    public function refuseIn(Validator $validator, array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if ($this->holdsKey($data[$field] ?? null)) {
                $validator->errors()->add($field, $this->message());
            }
        }
    }

    /** A validation rule for a field stored as it is: a comment, a name. */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (app(self::class)->holdsKey($value)) {
                $fail(app(self::class)->message());
            }
        };
    }

    /** The error text for the person who tried to shorten the link, in the current language. */
    public function message(): string
    {
        return __(self::MESSAGE);
    }

    /**
     * Read the fragment the way NoteHop does (new URLSearchParams(location.hash.slice(1))): one leading "?" is
     * dropped ("#?k=..." is the k parameter), pairs split on &, names and values percent-decoded, so "%6B=..." is the
     * k parameter too. A parameter with an empty value carries nothing.
     */
    public static function fragmentHasSecret(string $fragment): bool
    {
        $fragment = preg_replace('/[\t\r\n]/', '', ltrim($fragment, '#'));
        if (str_starts_with($fragment, '?')) {
            $fragment = substr($fragment, 1);
        }
        foreach (explode('&', $fragment) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if (in_array(urldecode($name), self::SECRET_PARAMS, true) && urldecode($value) !== '') {
                return true;
            }
        }

        return false;
    }

    /** The address without its fragment: what a server may log, send to a checker or show in an email. */
    public static function withoutFragment(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $at = strpos($url, '#');

        return $at === false ? $url : substr($url, 0, $at);
    }

    /**
     * The input of a refused form without the fragment of any encrypted-note address in it, at any depth. Everything
     * else is returned as it was. Used so the old input flashed to the session does not hold a key.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    public static function scrubInput(array $input): array
    {
        $notes = app(self::class);
        foreach ($input as $name => $value) {
            if (is_array($value)) {
                $input[$name] = self::scrubInput($value);
            } elseif (is_string($value)) {
                // the whole value is a note address, or a note address sits inside a longer text (a report, a message)
                $input[$name] = $notes->matches($value) ? self::withoutFragment($value) : $notes->scrubAll($value);
            }
            if (is_string($name) && ($clean = $notes->scrubAll($name)) !== $name) {
                $input[$clean] = $input[$name];
                unset($input[$name]);
            }
        }

        return $input;
    }

    /**
     * The address with only the secret parameters (k, a) taken out of its fragment; any other part of the fragment
     * stays ("#new=1&k=KEY" becomes "#new=1", "#k=KEY" becomes nothing). A leading "?" in the fragment is kept.
     */
    public static function withoutSecrets(string $url): string
    {
        if (preg_match(self::INTENT_FALLBACK, $url, $intent, PREG_OFFSET_CAPTURE)) {
            [$value, $offset] = $intent[1];

            return substr($url, 0, $offset).rawurlencode(self::withoutSecrets(rawurldecode($value))).substr($url, $offset + strlen($value));
        }
        $at = strpos($url, '#');
        if ($at === false) {
            return $url;
        }
        $fragment = substr($url, $at + 1);
        $mark = str_starts_with($fragment, '?') ? '?' : '';
        $kept = array_filter(
            explode('&', $mark === '' ? $fragment : substr($fragment, 1)),
            fn (string $pair) => ! in_array(urldecode(explode('=', preg_replace('/[\t\r\n]/', '', $pair), 2)[0]), self::SECRET_PARAMS, true),
        );
        $kept = array_filter($kept, fn (string $pair) => $pair !== '');

        return substr($url, 0, $at).($kept === [] ? '' : '#'.$mark.implode('&', $kept));
    }

    /**
     * Take the secret parameters out of every NoteHop address in a stored text (a log line, a JSON payload). Only an
     * address on a NoteHop host is touched, so a "#a=1" on another site stays as it was; JSON escapes ("\/", "\uff4e")
     * are read before the host is compared. Used by links:scrub-note-keys.
     */
    public static function scrubText(string $text): string
    {
        $notes = app(self::class);

        return preg_replace_callback(
            '~(https?:[^\s"\'<>#]*)#([^\s"\'<>\\\\()\[\]{}]*)~i',
            function (array $m) use ($notes) {
                $plain = str_contains($m[1], '\\') ? (json_decode('"'.$m[1].'"') ?? stripslashes($m[1])) : $m[1];
                if (! is_string($plain) || ! $notes->matches($plain.'#'.$m[2])) {
                    return $m[0];
                }
                $rest = substr(self::withoutSecrets('#'.$m[2]), 1);

                return $m[1].($rest === '' ? '' : '#'.$rest);
            },
            $text,
        ) ?? $text;
    }

    /**
     * scrubText for a stored value of any kind: JSON is cleaned string by string and written back the way Laravel
     * writes it, and an address scrubText cannot see (no scheme, written with entities or escapes) is cut out as well.
     * The result only differs from the value when the value held a key; holdsKey() tells whether it still does.
     */
    public function scrubAll(string $text): string
    {
        if (($data = self::jsonArray($text)) !== null) {
            $clean = $this->scrubArray($data);

            return $clean === $data ? $text : (json_encode($clean) ?: $text);
        }
        $out = self::scrubText($text);
        if (! $this->holdsKey($out)) {
            return $out;
        }
        foreach (self::readings($out) as $reading) {
            $clean = $this->inText()->scrub($reading);
            if (! $this->holdsKey($clean)) {
                return $clean;
            }
        }

        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function scrubArray(array $data): array
    {
        $clean = [];
        foreach ($data as $name => $value) {
            $clean[is_string($name) ? $this->scrubAll($name) : $name] = match (true) {
                is_array($value) => $this->scrubArray($value),
                is_string($value) => $this->scrubAll($value),
                default => $value,
            };
        }

        return $clean;
    }

    /** The reader of addresses inside a longer text (hop/safety), with this app's host list. */
    private function inText(): AddressesInText
    {
        return $this->inText ??= new AddressesInText($this->hosts());
    }

    /** The JSON array or object a text holds, or null when it holds none. */
    private static function jsonArray(string $text): ?array
    {
        if (! preg_match('/^\s*[\[{]/', $text)) {
            return null;
        }
        $data = json_decode($text, true);

        return is_array($data) ? $data : null;
    }

    /**
     * The text as it is, and as a browser would read it: HTML entities and CSS or JavaScript escapes undone (a few
     * rounds, they can be nested), and each of those without tabs and line breaks.
     *
     * @return list<string>
     */
    private static function readings(string $text): array
    {
        $readings = [$text];
        for ($round = 0; $round < 3; $round++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = preg_replace_callback(
                '/\\\\(?:u([0-9a-f]{4})|([0-9a-f]{1,6})[ \t\r\n\f]?)/i',
                fn (array $m) => mb_chr((int) hexdec($m[1] !== '' ? $m[1] : $m[2]), 'UTF-8') ?: $m[0],
                $decoded,
            ) ?? $decoded;
            $decoded = preg_replace('/\\\\([^0-9a-f\r\n])/i', '$1', $decoded) ?? $decoded;
            if ($decoded === $text) {
                break;
            }
            $readings[] = $text = $decoded;
        }
        foreach ($readings as $reading) {
            $readings[] = (string) preg_replace('/[\t\r\n]/', '', $reading);
        }

        return array_values(array_unique($readings));
    }

    /** True when scrubText would change the text, read as it is and with HTML entities decoded (a debug page). */
    public static function textHoldsSecret(string $text): bool
    {
        if (! str_contains($text, '#') && ! str_contains($text, '%23') && ! str_contains($text, '&#')) {
            return false;
        }
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        return self::scrubText($text) !== $text || self::scrubText($decoded) !== $decoded;
    }

    private static function hostOf(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }
        $host = parse_url(str_contains($url, '://') ? trim($url) : 'https://'.trim($url), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? self::normalizeHost($host) : null;
    }

    /** What a browser does before it reads an address: drop tabs and line breaks, trim control characters and spaces. */
    private static function asBrowserReads(string $url): string
    {
        return preg_replace('/^[\x00-\x20]+|[\x00-\x20]+$/', '', preg_replace('/[\t\r\n]/', '', $url)) ?? '';
    }

    /**
     * The host the way a browser reads it, so the comparison does not depend on how it was typed: percent-decoded,
     * mapped by UTS 46 (full-width and other compatibility letters, U+3002 and similar dots, soft hyphens and
     * zero-width characters are folded or dropped), lower case, no trailing dot, no www. Needs ext-intl; without it
     * only the plain forms are caught, which is why composer.json requires it.
     */
    private static function normalizeHost(string $host): string
    {
        $host = rawurldecode(trim($host, '[]'));
        if (function_exists('idn_to_ascii') && ($ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46)) !== false) {
            $host = $ascii;
        }

        return preg_replace('/^www\./', '', rtrim(strtolower($host), '.'));
    }
}
