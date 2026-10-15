<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Admin Control Panel</x-slot>
    @php
        $s = $adminStats;
        $heartbeat = $s['queue_heartbeat'] ? \Illuminate\Support\Carbon::parse($s['queue_heartbeat']) : null;
        $queueOk = $heartbeat && $heartbeat->gt(now()->subMinutes(10));
        $scanAt = $s['abuse_last_scan'] ? \Illuminate\Support\Carbon::parse($s['abuse_last_scan']) : null;
        $scanOk = $scanAt && $scanAt->gt(now()->subHours(3));
        $delta = $s['today_platform_clicks'] - $s['yesterday_platform_clicks'];
        $trendMax = max(1, $clickTrend->max());
    @endphp

    <x-page-header title="Admin Control Panel" subtitle="Platform health, moderation and growth at a glance." />

    <x-flash />

    {{-- Key metrics --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <a href="{{ route('admin.users.index') }}" class="stat transition hover:-translate-y-0.5">
            <p class="stat-label">Users</p>
            <p class="stat-value">{{ number_format($s['total_users']) }}</p>
            <p class="text-[11px] text-muted">+{{ number_format($s['new_users_week']) }} this week</p>
        </a>
        <a href="{{ route('admin.links.index') }}" class="stat transition hover:-translate-y-0.5">
            <p class="stat-label">Links</p>
            <p class="stat-value">{{ number_format($s['total_platform_links']) }}</p>
            <p class="text-[11px] text-muted">{{ number_format($s['live_links']) }} live</p>
        </a>
        <a href="{{ route('admin.analytics.global') }}" class="stat transition hover:-translate-y-0.5">
            <p class="stat-label">Total clicks</p>
            <p class="stat-value">{{ number_format($s['total_platform_clicks']) }}</p>
            <p class="text-[11px] text-muted">all time</p>
        </a>
        <div class="stat">
            <p class="stat-label">Clicks today</p>
            <p class="stat-value">{{ number_format($s['today_platform_clicks']) }}</p>
            <p class="text-[11px] {{ $delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                {{ $delta >= 0 ? '+' : '' }}{{ number_format($delta) }} vs yesterday
            </p>
        </div>
    </div>

    {{-- Bento: trend + needs attention --}}
    <div class="grid gap-3 lg:grid-cols-3">
        <section class="glass-card flex flex-col lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="section-title">Clicks, last 14 days</h2>
                <span class="text-xs text-muted">{{ number_format($clickTrend->sum()) }} total</span>
            </div>
            <div class="mt-6 flex h-40 items-end gap-1.5 lg:h-auto lg:min-h-40 lg:flex-1" role="img" aria-label="Clicks per day for the last 14 days">
                @foreach ($clickTrend as $day => $total)
                    <div class="group relative flex h-full flex-1 flex-col justify-end">
                        <div class="rounded-md bg-indigo-500/70 transition group-hover:bg-indigo-500 {{ $loop->last ? '!bg-indigo-500' : '' }}"
                             style="height: {{ max(2, round($total / $trendMax * 100)) }}%"></div>
                        <span class="pointer-events-none absolute -top-6 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-1.5 py-0.5 text-[10px] text-white group-hover:block dark:bg-white dark:text-slate-900">
                            {{ \Illuminate\Support\Carbon::parse($day)->format('d M') }} · {{ number_format($total) }}
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="mt-1.5 flex justify-between text-[10px] text-muted">
                <span>{{ \Illuminate\Support\Carbon::parse($clickTrend->keys()->first())->format('d M') }}</span>
                <span>Today</span>
            </div>
        </section>

        <section class="glass-card">
            <h2 class="section-title">Needs attention</h2>
            <ul class="mt-3 space-y-1.5 text-sm">
                @php
                    $rows = [
                        ['Open abuse reports', $s['open_reports'], route('admin.abuse.index'), $s['open_reports'] > 0],
                        ['Open abuse findings', $s['open_flags'], route('admin.abuse.index'), $s['open_flags'] > 0],
                        ['Suspended users', $s['suspended_users'], route('admin.users.index', ['status' => 'suspended']), false],
                        ['Disabled links', $s['inactive_links'], route('admin.links.index', ['status' => 'inactive']), false],
                    ];
                @endphp
                @foreach ($rows as [$label, $count, $href, $alert])
                    <li>
                        <a href="{{ $href }}" class="glass-inset flex items-center justify-between px-3 py-2 transition hover:opacity-80">
                            <span class="text-ink">{{ $label }}</span>
                            <span class="{{ $alert ? 'chip-red' : 'chip-gray' }} tabular-nums">{{ number_format($count) }}</span>
                        </a>
                    </li>
                @endforeach
                <li class="glass-inset flex items-center justify-between gap-2 px-3 py-2" x-data="{ restarting: false }">
                    <span class="min-w-0">
                        <span class="block text-ink">Queue worker</span>
                        <span class="block truncate text-[11px] text-muted">{{ $heartbeat ? 'Ping '.$heartbeat->diffForHumans() : 'No heartbeat recorded' }}</span>
                    </span>
                    <span class="flex shrink-0 items-center gap-1.5">
                        <span class="{{ $queueOk ? 'chip-green' : 'chip-red' }}">{{ $queueOk ? 'Online' : 'Stale' }}</span>
                        <form method="POST" action="{{ route('admin.queue.restart') }}" @submit="restarting = true">
                            @csrf
                            <button type="submit" class="icon-btn !h-7 !w-7" :disabled="restarting" title="Restart queue worker" aria-label="Restart queue worker">
                                <svg class="h-3.5 w-3.5" :class="{ 'animate-spin': restarting }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                        </form>
                    </span>
                </li>
                <li>
                    <a href="{{ route('admin.abuse.index') }}" class="glass-inset flex items-center justify-between gap-2 px-3 py-2 transition hover:opacity-80">
                        <span class="min-w-0">
                            <span class="block text-ink">Abuse scan</span>
                            <span class="block truncate text-[11px] text-muted">{{ $scanAt ? 'Ran '.$scanAt->diffForHumans() : 'Has never run' }}</span>
                        </span>
                        <span class="{{ $scanOk ? 'chip-green' : 'chip-amber' }}">{{ $scanOk ? 'OK' : 'Check cron' }}</span>
                    </a>
                </li>
            </ul>
        </section>
    </div>

    {{-- Recent activity --}}
    <div class="grid gap-3 lg:grid-cols-3">
        <section class="glass overflow-hidden rounded-2xl lg:col-span-2">
            <div class="flex items-center justify-between px-4 pb-2 pt-4">
                <h2 class="section-title">Newest links</h2>
                <a href="{{ route('admin.links.index') }}" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-300">View all</a>
            </div>
            <div class="divide-glass">
                @forelse ($recentPlatformLinks as $link)
                    <div class="list-row">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <a href="{{ $link->short_url }}" target="_blank" rel="noopener" class="truncate font-mono text-sm font-semibold text-ink hover:text-indigo-600 dark:hover:text-indigo-300">/{{ $link->slug }}</a>
                                @if ($link->status !== 'active')
                                    <span class="chip-red">Disabled</span>
                                @elseif ($link->isExpired())
                                    <span class="chip-amber">Expired</span>
                                @endif
                            </div>
                            <p class="truncate text-xs text-muted">{{ $link->target_url }}</p>
                        </div>
                        <div class="hidden w-40 shrink-0 text-right sm:block">
                            <p class="truncate text-xs text-ink">{{ $link->user->name ?? 'Guest' }}</p>
                            <p class="text-[11px] text-muted">{{ $link->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted">No links yet.</p>
                @endforelse
            </div>
        </section>

        <section class="glass overflow-hidden rounded-2xl">
            <div class="flex items-center justify-between px-4 pb-2 pt-4">
                <h2 class="section-title">Newest users</h2>
                <a href="{{ route('admin.users.index') }}" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-300">Manage</a>
            </div>
            <div class="divide-glass">
                @forelse ($recentUsers as $user)
                    <a href="{{ route('admin.users.inspect', $user) }}" class="list-row">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-xs font-semibold text-indigo-600 dark:text-indigo-300">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-ink">{{ $user->name }}</span>
                            <span class="block truncate text-xs text-muted">{{ $user->email }}</span>
                        </span>
                        @if ($user->status !== 'active')
                            <span class="chip-red">Suspended</span>
                        @else
                            <span class="text-[11px] tabular-nums text-muted">{{ $user->links_count }} links</span>
                        @endif
                    </a>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted">No users yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="grid gap-3 lg:grid-cols-3">
        <section class="glass-card">
            <h2 class="section-title mb-3">Top countries</h2>
            <x-analytics.rank-list :items="$topPlatformCountries->map(fn ($c) => ['label' => $c->country, 'flag' => $c->country, 'value' => (int) $c->total])" empty="No country data yet." />
        </section>

        <section class="glass overflow-hidden rounded-2xl">
            <div class="flex items-center justify-between px-4 pb-2 pt-4">
                <h2 class="section-title">Latest reports</h2>
                <a href="{{ route('admin.abuse.index') }}" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-300">Abuse</a>
            </div>
            <div class="divide-glass">
                @forelse ($openReports as $report)
                    <div class="list-row">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-mono text-sm font-semibold text-ink">{{ $report->slug ? '/'.$report->slug : 'URL' }}</p>
                            <p class="truncate text-xs text-muted">{{ \Illuminate\Support\Str::limit($report->reason, 60) }}</p>
                        </div>
                        <span class="{{ $report->status === 'open' ? 'chip-red' : 'chip-amber' }}">{{ ucfirst($report->status) }}</span>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted">No open reports.</p>
                @endforelse
            </div>
        </section>

        <section class="glass-card">
            <h2 class="section-title">Security rules</h2>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                <a href="{{ route('admin.ip-bans.index') }}" class="glass-inset px-2 py-3 transition hover:opacity-80">
                    <p class="text-lg font-semibold tabular-nums text-ink">{{ number_format($s['ip_bans']) }}</p>
                    <p class="text-[11px] text-muted">IP bans</p>
                </a>
                <a href="{{ route('admin.ip-watchlist.index') }}" class="glass-inset px-2 py-3 transition hover:opacity-80">
                    <p class="text-lg font-semibold tabular-nums text-ink">{{ number_format($s['ip_watchlist']) }}</p>
                    <p class="text-[11px] text-muted">Watchlist</p>
                </a>
                <a href="{{ route('admin.domain-blacklist.index') }}" class="glass-inset px-2 py-3 transition hover:opacity-80">
                    <p class="text-lg font-semibold tabular-nums text-ink">{{ number_format($s['domain_blacklist']) }}</p>
                    <p class="text-[11px] text-muted">Blocked domains</p>
                </a>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <a href="{{ route('admin.seo.edit') }}" class="btn-soft btn-sm">Site &amp; SEO</a>
                <a href="{{ route('admin.bio.index') }}" class="btn-soft btn-sm">Bio pages</a>
            </div>
        </section>
    </div>

    <x-link-creation-modal :folders="$folders" :tags="$tags" :isAdmin="true" />
</x-app-layout>
