<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Abuse</x-slot>
    @php
        $lastAt = isset($lastRun['at']) ? \Illuminate\Support\Carbon::parse($lastRun['at']) : null;
        // Hourly schedule: more than 3h without a run means the scheduler is not running.
        $stale = ! $lastAt || $lastAt->lt(now()->subHours(3));
    @endphp

    <x-page-header title="Abuse" subtitle="Automated findings from abuse:scan and reports from visitors.">
        <x-slot name="actions">
            <form method="POST" action="{{ route('admin.abuse.scan') }}">
                @csrf
                <button type="submit" class="btn-primary btn-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Scan now
                </button>
            </form>
        </x-slot>
    </x-page-header>

    <x-flash />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="stat">
            <p class="stat-label">Open findings</p>
            <p class="stat-value">{{ number_format($counts['open_flags']) }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Auto-disabled</p>
            <p class="stat-value">{{ number_format($counts['disabled']) }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Open reports</p>
            <p class="stat-value">{{ number_format($counts['open_reports']) }}</p>
        </div>
        <div class="stat {{ $stale ? 'border-amber-400/50' : '' }}">
            <p class="stat-label">Last scan</p>
            <p class="stat-value {{ $stale ? 'text-amber-600 dark:text-amber-300' : '' }}">{{ $lastAt ? $lastAt->diffForHumans(null, true) : 'Never' }}</p>
            @if ($lastRun)
                <p class="text-[11px] text-muted">{{ $lastRun['mode'] ?? '' }} · {{ $lastRun['stats']['scanned'] ?? 0 }} scanned</p>
            @endif
        </div>
    </div>

    @if ($stale)
        <div class="glass flex items-start gap-3 rounded-2xl border-amber-400/40 px-4 py-3 text-sm">
            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-xs font-bold text-amber-600 dark:text-amber-300">!</span>
            <p class="text-ink">
                The hourly scan has not run in the last 3 hours, so new links are not being checked.
                Make sure the server cron runs <code class="rounded bg-slate-500/15 px-1 font-mono text-xs">* * * * * php artisan schedule:run</code>,
                or use <strong>Scan now</strong>.
            </p>
        </div>
    @endif

    <section class="glass overflow-hidden rounded-2xl">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 pb-2 pt-4">
            <h2 class="section-title">Automated findings</h2>
            <span class="text-xs text-muted">Restore = false positive, it won't be flagged again.</span>
        </div>
        @if ($flags->isEmpty())
            <p class="px-4 pb-8 pt-4 text-center text-sm text-muted">No open findings.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Signals</th>
                            <th>Action</th>
                            <th class="hidden md:table-cell">Owner</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($flags as $flag)
                            <tr>
                                <td class="max-w-sm">
                                    <p class="text-sm font-semibold text-ink">{{ ucfirst(str_replace('_', ' ', $flag->subject_type)) }} #{{ $flag->subject_id }}</p>
                                    <p class="truncate text-xs text-muted" title="{{ $flag->summary }}">{{ $flag->summary }}</p>
                                    <p class="text-[11px] text-muted">{{ $flag->created_at?->diffForHumans() }}</p>
                                </td>
                                <td>
                                    @if ($flag->isCsam())
                                        <span class="chip bg-rose-600 text-white">CSAM: do not open, report</span>
                                    @endif
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($flag->signals ?? [] as $signal)
                                            <span class="chip-amber">{{ str_replace('_', ' ', $signal) }}</span>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-[11px] text-muted">score {{ $flag->score }}</p>
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="{{ $flag->action === 'flagged' ? 'chip-gray' : 'chip-red' }}">{{ ucfirst($flag->action) }}</span>
                                    @if ($flag->status === 'confirmed')
                                        <span class="chip-green">Confirmed</span>
                                    @endif
                                </td>
                                <td class="hidden text-xs md:table-cell">
                                    @if ($flag->user)
                                        <a href="{{ route('admin.users.inspect', $flag->user) }}" class="text-indigo-600 hover:underline dark:text-indigo-300">{{ $flag->user->email }}</a>
                                        <span class="block text-muted">{{ $flag->user->status }}</span>
                                    @else
                                        <span class="text-muted">guest</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <div class="inline-flex gap-1.5">
                                        @if ($flag->status === 'open')
                                            <form method="POST" action="{{ route('admin.abuse.flags.confirm', $flag) }}">
                                                @csrf
                                                <button class="btn-primary btn-sm">Confirm</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.abuse.flags.restore', $flag) }}" data-confirm="Restore this item and stop flagging it?">
                                            @csrf
                                            <button class="btn-soft btn-sm">Restore</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($flags->hasPages())
                <div class="border-t border-slate-500/10 px-4 py-3">{{ $flags->links() }}</div>
            @endif
        @endif
    </section>

    <section class="glass overflow-hidden rounded-2xl">
        <div class="px-4 pb-2 pt-4">
            <h2 class="section-title">Visitor reports</h2>
        </div>
        @if ($reports->isEmpty())
            <p class="px-4 pb-8 pt-4 text-center text-sm text-muted">No reports yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>Link</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th class="text-right">Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reports as $report)
                            @php $chip = ['open' => 'chip-red', 'investigating' => 'chip-amber', 'closed' => 'chip-gray'][$report->status] ?? 'chip-gray'; @endphp
                            <tr>
                                <td class="max-w-xs">
                                    <p class="font-mono text-sm font-semibold text-ink">{{ $report->slug ? '/'.$report->slug : 'n/a' }}</p>
                                    <p class="truncate text-xs text-muted" title="{{ $report->url }}">{{ $report->url }}</p>
                                    <p class="text-[11px] text-muted">{{ $report->created_at?->diffForHumans() }} · {{ $report->ip_address }}</p>
                                </td>
                                <td class="max-w-sm text-sm text-ink">{{ \Illuminate\Support\Str::limit($report->reason, 160) }}</td>
                                <td><span class="{{ $chip }}">{{ ucfirst($report->status) }}</span></td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.abuse.update', $report) }}" class="inline-flex items-center gap-1.5">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" aria-label="Report status" class="input !w-auto !py-1.5 text-xs">
                                            @foreach (['open', 'investigating', 'closed'] as $option)
                                                <option value="{{ $option }}" @selected($report->status === $option)>{{ ucfirst($option) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn-soft btn-sm">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($reports->hasPages())
                <div class="border-t border-slate-500/10 px-4 py-3">{{ $reports->links() }}</div>
            @endif
        @endif
    </section>
</x-app-layout>
