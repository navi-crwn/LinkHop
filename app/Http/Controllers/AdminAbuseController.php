<?php

namespace App\Http\Controllers;

use App\Console\Commands\AbuseScan;
use App\Models\AbuseFlag;
use App\Models\AbuseReport;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\User;
use App\Services\LinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAbuseController extends Controller
{
    public function index(): View
    {
        $reports = AbuseReport::latest()->paginate(20, ['*'], 'reports');
        $flags = AbuseFlag::with('user:id,email,status')
            ->where('status', '!=', AbuseFlag::STATUS_DISMISSED)
            ->latest()
            ->paginate(25, ['*'], 'flags');

        $lastRun = Cache::get(AbuseScan::LAST_RUN_KEY);
        $counts = [
            'open_flags' => AbuseFlag::where('status', AbuseFlag::STATUS_OPEN)->count(),
            'disabled' => AbuseFlag::where('action', AbuseFlag::ACTION_DISABLED)->where('status', '!=', AbuseFlag::STATUS_DISMISSED)->count(),
            'open_reports' => AbuseReport::where('status', 'open')->count(),
        ];

        return view('admin-abuse', compact('reports', 'flags', 'lastRun', 'counts'));
    }

    /**
     * Run abuse:scan over the last week right after this response is sent, for
     * when the scheduler is not running or an admin wants fresh findings now.
     */
    public function scan(): RedirectResponse
    {
        dispatch(fn () => Artisan::call('abuse:scan', ['--since' => '7d']))->afterResponse();

        return back()->with('status', 'Scan started for items from the last 7 days. Refresh in a minute to see new findings.');
    }

    public function update(Request $request, AbuseReport $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'investigating', 'closed'])],
        ]);
        $report->update(['status' => $data['status']]);

        return back()->with('status', 'Report updated.');
    }

    /**
     * Mark an automated finding as correct.
     */
    public function confirmFlag(AbuseFlag $flag): RedirectResponse
    {
        $flag->update(['status' => AbuseFlag::STATUS_CONFIRMED]);

        return back()->with('status', 'Finding confirmed.');
    }

    /**
     * False positive: re-enable the subject; abuse:scan will not act on it again.
     */
    public function restoreFlag(AbuseFlag $flag, LinkService $links): RedirectResponse
    {
        match ($flag->subject_type) {
            'link' => tap(Link::find($flag->subject_id), function (?Link $link) use ($links) {
                if ($link) {
                    $link->forceFill(['status' => Link::STATUS_ACTIVE])->save();
                    $links->forgetCache($link);
                }
            }),
            'bio_page' => BioPage::whereKey($flag->subject_id)->update(['is_published' => true]),
            'bio_link' => BioLink::whereKey($flag->subject_id)->update(['is_active' => true]),
            'user' => User::whereKey($flag->subject_id)->update(['status' => User::STATUS_ACTIVE]),
            default => null,
        };
        $flag->update(['status' => AbuseFlag::STATUS_DISMISSED]);

        return back()->with('status', 'Restored. The scanner will leave this item alone.');
    }
}
