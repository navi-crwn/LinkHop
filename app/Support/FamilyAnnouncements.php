<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The announcement card of the dashboard. The text is written once on the hub (HOP ID) and every app of the family
 * reads it: GET {hub}/announcements.json?app=linkhop, a public list with title, body, level and link.
 *
 * It is read by the server, never by the browser, and it can never cost a member a page: two seconds at most, a
 * copy kept for five minutes, a failed read remembered for a minute (so a hub that is down is asked once a minute,
 * not once per page), and the last good list served while the hub is away. No hub address, no request at all.
 */
final class FamilyAnnouncements
{
    public const FRESH_KEY = 'family-announcements:fresh';

    public const LAST_KEY = 'family-announcements:last';

    private const FRESH_SECONDS = 300;

    private const RETRY_SECONDS = 60;

    private const LEVELS = ['info', 'warning', 'critical'];

    /** The one card to show this member, or null: critical first, then the newest; a hidden card makes way for the next. */
    public static function card(User $user): ?array
    {
        $items = collect(self::items())
            ->reject(fn (array $item) => $item['level'] !== 'critical' && Reminders::hidden($user, 'ann-'.$item['id']))
            ->sortByDesc(fn (array $item) => [$item['level'] === 'critical' ? 1 : 0, $item['published_at']])
            ->values();

        return $items->first();
    }

    /** Announcements for this app that are still running. */
    public static function items(): array
    {
        $items = Cache::get(self::FRESH_KEY);
        if (! is_array($items)) {
            $items = self::fetch();
        }

        return array_values(array_filter($items, fn (array $item) => $item['expires_at'] === null || Carbon::parse($item['expires_at'])->isFuture()));
    }

    private static function fetch(): array
    {
        $hub = (string) config('hop.hub_url');
        if (! config('hop.family_announcements') || $hub === '') {
            return [];
        }
        try {
            $response = Http::acceptJson()->timeout(2)->connectTimeout(1)->get($hub.'/announcements.json', ['app' => config('hop.current')]);
            $list = $response->successful() ? $response->json('items') : null;
        } catch (Throwable) {
            $list = null;
        }
        if (! is_array($list)) {
            // The hub did not answer: keep what we last had, and do not ask again for a minute.
            $last = Cache::get(self::LAST_KEY, []);
            Cache::put(self::FRESH_KEY, $last, self::RETRY_SECONDS);

            return $last;
        }
        $items = self::clean($list);
        Cache::put(self::FRESH_KEY, $items, self::FRESH_SECONDS);
        Cache::put(self::LAST_KEY, $items, now()->addDays(7));

        return $items;
    }

    /** Only well-formed entries for this app, with the text cut to card size. Nothing from the hub is trusted as HTML. */
    private static function clean(array $list): array
    {
        $out = [];
        foreach (array_slice($list, 0, 20) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = (string) ($item['id'] ?? '');
            $title = is_string($item['title'] ?? null) ? trim($item['title']) : '';
            $apps = is_array($item['apps'] ?? null) ? $item['apps'] : [];
            if (! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) || $title === '' || ! in_array($item['level'] ?? null, self::LEVELS, true)
                || ! (in_array('*', $apps, true) || in_array(config('hop.current'), $apps, true))) {
                continue;
            }
            $url = is_string($item['url'] ?? null) && preg_match('#^https?://[^\s]+$#i', $item['url']) ? $item['url'] : null;
            $out[] = [
                'id' => $id,
                'level' => $item['level'],
                'title' => Str::limit($title, 160, '…'),
                'body' => Str::limit(is_string($item['body'] ?? null) ? trim($item['body']) : '', 300, '…'),
                'url' => $url,
                'published_at' => self::moment($item['published_at'] ?? null) ?? '1970-01-01T00:00:00+00:00',
                'expires_at' => self::moment($item['expires_at'] ?? null),
            ];
        }

        return $out;
    }

    private static function moment(mixed $value): ?string
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value)->toIso8601String() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
