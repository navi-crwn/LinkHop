<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The move of short links from hel.ink/{slug} to hel.ink/l/{slug}: the end date
 * of the old form (shortener.legacy_slug_until) and whether the notice still shows.
 */
class ShortLinkMove
{
    public static function until(): ?Carbon
    {
        $until = config('shortener.legacy_slug_until');

        return filled($until) ? Carbon::parse($until, config('app.timezone'))->endOfDay() : null;
    }

    /** The date as shown to people: "27 November 2026". */
    public static function untilLabel(): string
    {
        return self::until()?->isoFormat('D MMMM YYYY') ?? '';
    }

    /** True while the old form still redirects, i.e. until the end of the date. */
    public static function active(): bool
    {
        $until = self::until();

        return $until !== null && now()->lessThanOrEqualTo($until);
    }
}
