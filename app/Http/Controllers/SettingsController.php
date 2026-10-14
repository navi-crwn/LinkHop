<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $loginHistory = DB::table('users')
            ->where('id', $user->id)
            ->select('last_login_at', 'last_login_ip', 'last_login_country', 'last_login_city', 'last_login_provider', 'last_login_device', 'last_login_browser')
            ->first();
        $loginHistories = DB::table('login_histories')
            ->where('user_id', $user->id)
            ->orderBy('logged_in_at', 'desc')
            ->limit(5)
            ->get();
        $activityQuery = DB::table('activity_logs')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');
        if ($request->filled('activity_from')) {
            $activityQuery->whereDate('created_at', '>=', $request->activity_from);
        }
        if ($request->filled('activity_to')) {
            $activityQuery->whereDate('created_at', '<=', $request->activity_to);
        }
        $activityLogs = $activityQuery->limit(50)->get();
        $folders = $user->folders()->orderBy('name')->get();
        $tags = $user->tags()->orderBy('name')->get();

        return view('settings', [
            'loginHistory' => $loginHistory,
            'loginHistories' => $loginHistories,
            'activityLogs' => $activityLogs,
            'folders' => $folders,
            'tags' => $tags,
        ]);
    }

    public function exportData(Request $request)
    {
        $user = $request->user();
        $query = $user->links()->with(['folder', 'tags'])->withCount('clicks');
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('folder_id')) {
            if ($request->folder_id === 'default') {
                $query->whereNull('folder_id');
            } else {
                $query->where('folder_id', $request->folder_id);
            }
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }
        if ($request->filled('min_clicks')) {
            $query->having('clicks_count', '>=', $request->min_clicks);
        }
        $sortBy = $request->input('sort_by', 'created_at');
        if ($sortBy === 'most_clicked') {
            $query->orderByDesc('clicks_count');
        } elseif ($sortBy === 'least_clicked') {
            $query->orderBy('clicks_count');
        } elseif ($sortBy === 'newest') {
            $query->orderByDesc('created_at');
        } elseif ($sortBy === 'oldest') {
            $query->orderBy('created_at');
        } else {
            $query->orderByDesc('created_at');
        }
        $links = $query->get();
        $filename = 'helink-export-'.now()->format('Y-m-d-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];
        $callback = function () use ($links) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Short Link',
                'Destination URL',
                'Redirect Type',
                'Folder',
                'Tags',
                'Password Protected',
                'Status',
                'Total Clicks',
                'Unique Visitors',
                'Created At',
                'Expires At',
                'Last Clicked',
            ]);
            foreach ($links as $link) {
                $uniqueVisitors = $link->clicks()
                    ->distinct('ip_address')
                    ->count('ip_address');
                $lastClicked = $link->clicks()
                    ->latest('clicked_at')
                    ->first();
                fputcsv($file, csv_safe([
                    $link->short_url,
                    $link->target_url,
                    $link->redirect_type ?? '302',
                    $link->folder->name ?? 'Default',
                    $link->tags->pluck('name')->join(', '),
                    $link->requiresPassword() ? 'Yes' : 'No',
                    ucfirst($link->status),
                    $link->clicks_count,
                    $uniqueVisitors,
                    $link->created_at->format('Y-m-d H:i:s'),
                    $link->expires_at ? $link->expires_at->format('Y-m-d H:i:s') : 'Never',
                    $lastClicked ? $lastClicked->clicked_at->format('Y-m-d H:i:s') : 'Never',
                ]));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Everything stored about the account (right of access): profile, links, folders,
     * tags, bio pages with their blocks, recent sign-ins and the activity log.
     * Visitor click logs are not included: they describe visitors, not the account owner.
     */
    public function exportAccount(Request $request)
    {
        $user = $request->user();
        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => $user->only(['name', 'email', 'created_at', 'email_verified_at', 'last_login_at']),
            'links' => $user->links()->with(['folder:id,name', 'tags:id,name'])->get()
                ->map(fn ($link) => $link->only(['slug', 'target_url', 'status', 'redirect_type', 'custom_title', 'custom_description', 'expires_at', 'created_at', 'clicks'])
                    + ['folder' => $link->folder?->name, 'tags' => $link->tags->pluck('name')]),
            'folders' => $user->folders()->pluck('name'),
            'tags' => $user->tags()->pluck('name'),
            'bio_pages' => $user->bioPages()->with('links')->get()->map(fn ($page) => collect($page->makeHidden(['links', 'id', 'user_id'])->toArray())
                ->put('blocks', $page->links->map->makeHidden(['id', 'bio_page_id'])->values())),
            'login_history' => $user->loginHistories()->latest('logged_in_at')->get(['logged_in_at', 'ip_address', 'country', 'city', 'device', 'browser']),
            'activity_log' => $user->activityLogs()->latest()->get(['created_at', 'action', 'model', 'description']),
        ];

        return response()->streamDownload(
            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'helink-account-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json']
        );
    }

    public function exportAnalytics(Request $request)
    {
        return $this->exportData($request);
    }
}
