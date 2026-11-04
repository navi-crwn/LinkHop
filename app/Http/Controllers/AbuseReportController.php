<?php

namespace App\Http\Controllers;

use App\Models\AbuseReport;
use App\Services\TurnstileService;
use App\Support\EncryptedNoteLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AbuseReportController extends Controller
{
    public function __construct(private readonly TurnstileService $turnstile) {}

    public function create(Request $request): View
    {
        return view('report-abuse', [
            'prefill' => array_filter($request->only(['slug', 'url']), 'is_string'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // A reporter may paste the address of an encrypted note: keep the key out of the report, the admin mail and the form sent back.
        $request->merge(EncryptedNoteLink::scrubInput($request->all()));
        if (! $this->turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()
                ->withErrors(['turnstile' => 'Security verification failed. Please try again.'])
                ->withInput();
        }
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:120'],
            'url' => ['nullable', 'url', 'max:500'],
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $ip = $request->ip();
        $alreadyToday = AbuseReport::where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->count();
        if ($alreadyToday >= 3) {
            return back()->withErrors(['reason' => 'Too many reports have been submitted from this IP address. Please try again later.'])->withInput();
        }
        AbuseReport::create([
            ...$data,
            'ip_address' => $ip,
        ]);

        return back()->with('status', 'Thank you. Your report has been submitted.');
    }
}
