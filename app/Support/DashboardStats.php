<?php

namespace App\Support;

use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the dashboard home: clicks per day for a week against the week before,
 * a seven-day line for each link shown, and the getting-started checklist.
 */
class DashboardStats
{
    /** @return array{week: int[], previous: int, total: int, delta: ?int, max: int, unique: int} */
    public static function week(User $user): array
    {
        $daily = DB::table('link_clicks')
            ->join('links', 'links.id', '=', 'link_clicks.link_id')
            ->where('links.user_id', $user->id)
            ->where('link_clicks.clicked_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(link_clicks.clicked_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');
        $at = fn (int $ago) => (int) ($daily[now()->subDays($ago)->toDateString()] ?? 0);
        $week = array_map(fn ($ago) => $at($ago), range(6, 0));
        $previous = array_sum(array_map(fn ($ago) => $at($ago), range(13, 7)));
        $total = array_sum($week);
        // Distinct visitors over the seven days, each counted once a day (the hash rotates daily, see VisitorHash::daily).
        $unique = (int) DB::table('link_clicks')
            ->join('links', 'links.id', '=', 'link_clicks.link_id')
            ->where('links.user_id', $user->id)
            ->where('link_clicks.clicked_at', '>=', now()->subDays(6)->startOfDay())
            ->whereNotNull('link_clicks.visitor_hash')
            ->distinct()
            ->count('link_clicks.visitor_hash');

        return [
            'week' => $week,
            'previous' => $previous,
            'total' => $total,
            'delta' => $previous > 0 ? (int) round(($total - $previous) / $previous * 100) : null,
            'max' => max(1, ...$week),
            'unique' => $unique,
        ];
    }

    /**
     * Clicks per day over the last seven days for each of the given links, oldest first.
     *
     * @param  int[]  $linkIds
     * @return array<int, int[]>
     */
    public static function sparklines(array $linkIds): array
    {
        $out = array_fill_keys($linkIds, array_fill(0, 7, 0));
        if ($linkIds === []) {
            return $out;
        }
        $rows = DB::table('link_clicks')
            ->whereIn('link_id', $linkIds)
            ->where('clicked_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('link_id, DATE(clicked_at) as d, COUNT(*) as c')
            ->groupBy('link_id', 'd')
            ->get();
        $index = array_flip(array_map(fn ($ago) => now()->subDays($ago)->toDateString(), range(6, 0)));
        foreach ($rows as $row) {
            if (isset($index[$row->d])) {
                $out[$row->link_id][$index[$row->d]] = (int) $row->c;
            }
        }

        return $out;
    }

    /**
     * Six getting-started steps, each done or not, with where to do it.
     *
     * @return array{done: int, items: array<int, array{label: string, done: bool, route: string, params: array}>}
     */
    public static function checklist(User $user): array
    {
        $links = $user->links();
        $newest = (clone $links)->latest()->first();
        $edit = $newest ? ['links.edit', [$newest]] : ['links.create', []];
        $items = [
            ['label' => 'Create your first link', 'done' => (clone $links)->exists(), 'route' => 'links.create', 'params' => []],
            ['label' => 'See your first click', 'done' => (clone $links)->where('clicks', '>', 0)->exists(), 'route' => $newest ? 'links.show' : 'links.create', 'params' => $newest ? [$newest] : []],
            ['label' => 'Protect a link with a password or an expiry date', 'done' => (clone $links)->where(fn ($q) => $q->whereNotNull('password_hash')->orWhereNotNull('expires_at'))->exists(), 'route' => $edit[0], 'params' => $edit[1]],
            ['label' => 'Make a QR code your own', 'done' => (clone $links)->where(fn ($q) => $q->where('qr_fg_color', '!=', '#000000')->orWhere('qr_bg_color', '!=', '#ffffff')->orWhereNotNull('qr_logo_url'))->exists(), 'route' => $edit[0], 'params' => $edit[1]],
            ['label' => 'Set a title and picture for the share preview', 'done' => (clone $links)->where(fn ($q) => $q->whereNotNull('custom_title')->orWhereNotNull('custom_image_url'))->exists(), 'route' => $edit[0], 'params' => $edit[1]],
            ['label' => 'Organise with a folder or a tag', 'done' => Tag::where('user_id', $user->id)->exists() || Folder::where('user_id', $user->id)->where('is_default', false)->exists(), 'route' => 'folders.index', 'params' => []],
        ];

        return ['done' => count(array_filter(array_column($items, 'done'))), 'items' => $items];
    }
}
