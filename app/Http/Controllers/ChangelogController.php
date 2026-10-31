<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $history = json_decode(file_get_contents(resource_path('changelog.json')), true, flags: JSON_THROW_ON_ERROR);
        $entries = collect($history['entries']);
        $apps = $entries->pluck('app')->unique()->values();
        $selected = (string) $request->query('app', '');
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
}
