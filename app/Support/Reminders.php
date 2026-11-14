<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reminder cards a person can hide for a week (the "Hide · Shows again next week" button of the family's hop-ui
 * script, on any element with data-hop-remind="<key>"). A hidden card comes back after HIDE_DAYS while its task is
 * still open; the page stops rendering it for good once the task is done, whatever is stored here.
 */
final class Reminders
{
    /** The cards this app has. checklist: "Get the most out of LinkHop". The hub's announcements are ann-<id>. */
    public const KEYS = ['checklist'];

    /** A card this app knows: one of KEYS, or a family announcement (ann-<id>, at most 44 characters). */
    public static function known(string $key): bool
    {
        return in_array($key, self::KEYS, true) || preg_match('/^ann-[A-Za-z0-9_-]{1,40}$/', $key) === 1;
    }

    public const HIDE_DAYS = 7;

    /** Hidden right now: dismissed less than HIDE_DAYS ago. */
    public static function hidden(User $user, string $key): bool
    {
        $at = DB::table('reminder_dismissals')->where('user_id', $user->id)->where('key', $key)->value('dismissed_at');

        return $at !== null && Carbon::parse($at)->greaterThan(now()->subDays(self::HIDE_DAYS));
    }

    /** Hide the card from now; returns the moment it shows again. */
    public static function dismiss(User $user, string $key): Carbon
    {
        $now = now();
        DB::table('reminder_dismissals')->updateOrInsert(
            ['user_id' => $user->id, 'key' => $key],
            ['dismissed_at' => $now, 'updated_at' => $now, 'created_at' => $now],
        );

        return $now->copy()->addDays(self::HIDE_DAYS);
    }

    public static function forget(int $userId): int
    {
        return DB::table('reminder_dismissals')->where('user_id', $userId)->delete();
    }
}
