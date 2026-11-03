<?php

namespace App\Support;

use App\Models\HopApp;
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

    /** @var list<string>|null */
    private ?array $hosts = null;

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
        // Read the address the way a browser does, not the way parse_url does: tabs and line breaks are dropped and
        // a backslash ends the host like a slash, so "https://note.hel.ink\@example.com/#k=..." is a NoteHop address.
        $clean = preg_replace('/[\t\r\n]/', '', trim((string) $url));
        if (! preg_match('~^https?:[/\\\\]{2}([^/\\\\?#]*)~i', $clean, $m) || ($at = strpos($clean, '#')) === false) {
            return false;
        }
        $host = preg_replace('/:\d*$/', '', substr(strrchr('@'.$m[1], '@'), 1));

        return in_array(self::normalizeHost($host), $this->hosts(), true)
            && self::fragmentHasSecret(substr($clean, $at + 1));
    }

    /** The error text for the person who tried to shorten the link, in the current language. */
    public function message(): string
    {
        return __(self::MESSAGE);
    }

    /**
     * Read the fragment the way NoteHop does (URLSearchParams on location.hash): pairs split on &, names and values
     * percent-decoded, so "%6B=..." is the k parameter too. A parameter with an empty value carries nothing.
     */
    public static function fragmentHasSecret(string $fragment): bool
    {
        foreach (explode('&', ltrim($fragment, '#')) as $pair) {
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
     * Cut the fragment out of every address in a stored text (a log line, a JSON payload) whose fragment carries a
     * secret parameter. Only the fragment goes; the rest of the text stays as it was. Used by links:scrub-note-keys.
     */
    public static function scrubText(string $text): string
    {
        return preg_replace_callback(
            '/#([^\s"\'<>\\\\]*)/',
            fn (array $m) => self::fragmentHasSecret($m[1]) ? '' : $m[0],
            $text,
        ) ?? $text;
    }

    private static function hostOf(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }
        $host = parse_url(str_contains($url, '://') ? trim($url) : 'https://'.trim($url), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? self::normalizeHost($host) : null;
    }

    private static function normalizeHost(string $host): string
    {
        return preg_replace('/^www\./', '', rtrim(strtolower(trim($host, '[]')), '.'));
    }
}
