<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $history = self::history();
        $entries = collect($history['entries']);
        $apps = $entries->pluck('app')->unique()->values();
        $selected = (is_string($request->query('app')) ? $request->query('app') : '');
        if ($selected !== '') {
            $entries = $entries->where('app', $selected)->values();
        }
        $total = $entries->count();
        $pages = max(1, (int) ceil($total / 40));
        $page = max(1, min($pages, $request->integer('page', 1)));

        return view('changelog', [
            'entries' => $entries->slice(($page - 1) * 40, 40),
            'apps' => $apps, 'selected' => $selected, 'page' => $page,
            'pages' => $pages, 'total' => $total, 'sources' => $history['sources'],
        ]);
    }

    /**
     * The history the deploy built (storage/app/changelog.json, scripts/build-changelog.py --out), or the snapshot
     * committed in the repository when none was built or the built file is unreadable.
     *
     * @return array{entries: array<int, array<string, mixed>>, sources: array<string, mixed>}
     */
    public static function history(): array
    {
        $built = storage_path('app/changelog.json');
        if (is_file($built)) {
            $data = json_decode((string) @file_get_contents($built), true);
            if (is_array($data) && is_array($data['entries'] ?? null) && is_array($data['sources'] ?? null)) {
                return $data;
            }
        }

        return json_decode(file_get_contents(resource_path('changelog.json')), true, flags: JSON_THROW_ON_ERROR);
    }
}
