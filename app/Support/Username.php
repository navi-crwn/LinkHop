<?php

namespace App\Support;

use App\Models\User;
use App\Models\UsernameHistory;
use Illuminate\Support\Str;

/**
 * One rule for usernames across every HOP app: 3 to 30 characters, lowercase
 * ASCII letters, digits, dots, dashes and underscores, starting with a letter
 * or digit, no ".." and no trailing dot. Reserved names and names released
 * less than `hop.username_hold_days` ago by someone else are refused.
 */
class Username
{
    public const PATTERN = '/^[a-z0-9][a-z0-9._-]{2,29}$/';

    /** Lowercases and strips a leading "@". */
    public static function normalize(?string $raw): string
    {
        return strtolower(ltrim(trim((string) $raw), '@'));
    }

    /** Why `name` can't be used by `for` (null = the name is fine). */
    public static function problem(string $name, ?User $for = null): ?string
    {
        if (preg_match(self::PATTERN, $name) !== 1) {
            return 'Use 3 to 30 characters: lowercase letters, numbers, dots, dashes or underscores, starting with a letter or number.';
        }
        if (str_contains($name, '..') || str_ends_with($name, '.')) {
            return 'Dots can\'t be doubled or at the end.';
        }
        if (self::isReserved($name)) {
            return 'That username is reserved.';
        }
        $taken = User::query()->where('username', $name)->when($for, fn ($q) => $q->whereKeyNot($for->id))->exists();
        if ($taken) {
            return 'That username is taken.';
        }
        if (self::isHeld($name, $for)) {
            return 'That username was used recently and is not available yet.';
        }

        return null;
    }

    public static function isReserved(string $name): bool
    {
        if (in_array($name, config('hop.reserved_usernames', []), true)) {
            return true;
        }
        foreach (config('hop.reserved_username_prefixes', []) as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Released by someone else within the hold window. */
    public static function isHeld(string $name, ?User $for = null): bool
    {
        $since = now()->subDays((int) config('hop.username_hold_days', 90));

        return UsernameHistory::query()
            ->where('username', $name)
            ->where('released_at', '>=', $since)
            ->when($for, fn ($q) => $q->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $for->id)))
            ->exists();
    }

    /**
     * A free username made from a display name or email: "Rani Putri" ->
     * "rani.putri", then "rani.putri2"… or a random tail when all else fails.
     */
    public static function suggest(string $nameOrEmail, ?User $for = null): string
    {
        $base = Str::contains($nameOrEmail, '@') ? Str::before($nameOrEmail, '@') : $nameOrEmail;
        $base = strtolower(Str::ascii($base));
        $base = preg_replace('/[^a-z0-9._-]+/', '.', $base) ?? '';
        $base = trim(preg_replace('/\.{2,}/', '.', $base) ?? '', '._-');
        $base = substr($base, 0, 24);
        if (strlen($base) < 3 || preg_match('/^[a-z0-9]/', $base) !== 1) {
            $base = 'user';
        }
        $candidates = [$base];
        for ($i = 2; $i <= 9; $i++) {
            $candidates[] = $base.$i;
        }
        foreach ($candidates as $c) {
            if (self::problem($c, $for) === null) {
                return $c;
            }
        }
        do {
            $c = substr($base, 0, 20).'-'.strtolower(Str::random(5));
        } while (self::problem($c, $for) !== null);

        return $c;
    }
}
