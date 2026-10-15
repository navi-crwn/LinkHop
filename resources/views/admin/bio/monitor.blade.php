<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Bio monitoring</x-slot>
    <x-page-header title="Bio monitoring" subtitle="Views and clicks across every link-in-bio page.">
        <x-slot name="actions">
            <select name="period" aria-label="Period" class="input !w-auto !py-1.5 text-sm" data-nav="?period={value}">
                @foreach ([7, 14, 30, 60, 90] as $days)
                    <option value="{{ $days }}" @selected((int) $period === $days)>Last {{ $days }} days</option>
                @endforeach
            </select>
            <div class="relative" x-data="{ open: false }">
                <button type="button" class="btn-soft btn-sm" @click="open = !open">Export</button>
                <div x-show="open" x-cloak @click.outside="open = false" x-transition class="glass-strong absolute right-0 z-20 mt-2 w-44 rounded-xl py-1 text-sm">
                    <a href="{{ route('admin.bio.export', ['type' => 'bio_pages']) }}" class="block px-3 py-1.5 text-ink hover:bg-slate-500/10">Bio pages (CSV)</a>
                    <a href="{{ route('admin.bio.export', ['type' => 'clicks']) }}" class="block px-3 py-1.5 text-ink hover:bg-slate-500/10">Clicks (CSV)</a>
                </div>
            </div>
            <a href="{{ route('admin.bio.index') }}" class="btn-primary btn-sm">Manage pages</a>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="stat">
            <p class="stat-label">Bio pages</p>
            <p class="stat-value">{{ number_format($stats['total_bio_pages']) }}</p>
            <p class="text-xs text-muted">{{ number_format($stats['published']) }} published · {{ number_format($stats['draft']) }} draft</p>
        </div>
        <div class="stat">
            <p class="stat-label">Views</p>
            <p class="stat-value">{{ number_format($stats['total_views']) }}</p>
            <p class="text-xs text-muted">{{ number_format($stats['unique_users']) }} creators</p>
        </div>
        <div class="stat">
            <p class="stat-label">Clicks</p>
            <p class="stat-value">{{ number_format($stats['total_clicks']) }}</p>
            <p class="text-xs text-emerald-600 dark:text-emerald-400">+{{ number_format($periodStats['period_clicks']) }} in {{ $period }} days</p>
        </div>
        <div class="stat">
            <p class="stat-label">Active links</p>
            <p class="stat-value">{{ number_format($stats['active_links']) }}</p>
            <p class="text-xs text-muted">of {{ number_format($stats['total_links']) }} · +{{ number_format($periodStats['new_bio_pages']) }} pages in {{ $period }}d</p>
        </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-3">
        <section class="glass-card lg:col-span-2">
            <h2 class="section-title">Clicks</h2>
            <div class="mt-3 h-56"><canvas id="clicksChart"></canvas></div>
        </section>
        <section class="glass-card">
            <h2 class="section-title">Countries</h2>
            <x-analytics.rank-list class="mt-3" :limit="6" :items="$clicksByCountry->map(fn ($c) => ['label' => country_name($c->country) ?? $c->country, 'value' => (int) $c->count, 'flag' => $c->country])" empty="No clicks in this period." />
        </section>
    </div>

    <div class="grid items-start gap-3 lg:grid-cols-3">
        <section class="glass overflow-hidden rounded-2xl lg:col-span-2">
            <h2 class="section-title px-4 pb-2 pt-4">Top bio pages</h2>
            <div class="overflow-x-auto">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th class="hidden sm:table-cell">Owner</th>
                            <th class="text-right">Links</th>
                            <th class="text-right">Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topBioPages as $bio)
                            <tr>
                                <td class="max-w-[12rem]">
                                    <a href="{{ route('admin.bio.show', $bio) }}" class="block truncate text-sm font-semibold text-ink hover:underline">{{ $bio->title }}</a>
                                    <p class="truncate text-xs text-muted">/b/{{ $bio->slug }}</p>
                                </td>
                                <td class="hidden max-w-[10rem] truncate text-sm text-muted sm:table-cell">{{ $bio->user->name ?? '—' }}</td>
                                <td class="text-right tabular-nums text-muted">{{ number_format($bio->links_count) }}</td>
                                <td class="text-right font-semibold tabular-nums text-ink">{{ number_format($bio->view_count) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-sm text-muted">No bio pages yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="space-y-3">
            <section class="glass-card">
                <h2 class="section-title">Devices</h2>
                <x-analytics.rank-list class="mt-3" color="bg-sky-500/70" :items="$clicksByDevice->map(fn ($d) => ['label' => ucfirst($d->device), 'value' => (int) $d->count])" empty="No clicks in this period." />
            </section>
            <section class="glass-card">
                <h2 class="section-title">Browsers</h2>
                <x-analytics.rank-list class="mt-3" color="bg-violet-500/70" :items="$clicksByBrowser->map(fn ($b) => ['label' => $b->browser, 'value' => (int) $b->count])" empty="No clicks in this period." />
            </section>
        </div>
    </div>

    <div class="grid items-start gap-3 md:grid-cols-2 xl:grid-cols-4">
        <section class="glass-card">
            <h2 class="section-title">Top links</h2>
            <x-analytics.rank-list class="mt-3" color="bg-emerald-500/70" :items="$topLinks->map(fn ($l) => ['label' => $l->title, 'value' => (int) $l->click_count, 'sub' => $l->bioPage ? '/b/'.$l->bioPage->slug : null])" empty="No link clicks yet." />
        </section>
        <section class="glass-card">
            <h2 class="section-title">Top creators</h2>
            <x-analytics.rank-list class="mt-3" color="bg-amber-500/70" :items="$topCreators->map(fn ($u) => ['label' => $u->name, 'value' => (int) $u->bio_pages_count, 'href' => route('admin.users.inspect', $u)])" empty="No creators yet." />
        </section>
        <section class="glass overflow-hidden rounded-2xl">
            <h2 class="section-title px-4 pb-2 pt-4">Newest pages</h2>
            <ul class="divide-glass">
                @forelse ($recentBioPages as $bio)
                    <li class="flex items-center justify-between gap-2 px-4 py-2">
                        <a href="{{ route('admin.bio.show', $bio) }}" class="min-w-0">
                            <span class="block truncate text-sm text-ink hover:underline">{{ $bio->title }}</span>
                            <span class="block truncate text-xs text-muted">{{ $bio->user->name ?? '—' }} · {{ $bio->created_at->diffForHumans(null, true) }}</span>
                        </a>
                        <span class="{{ $bio->is_published ? 'chip-green' : 'chip-gray' }} shrink-0">{{ $bio->is_published ? 'Live' : 'Draft' }}</span>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-muted">No bio pages yet.</li>
                @endforelse
            </ul>
        </section>
        <section class="glass overflow-hidden rounded-2xl">
            <h2 class="section-title px-4 pb-2 pt-4">Latest clicks</h2>
            <ul class="divide-glass">
                @forelse ($recentClicks->take(8) as $click)
                    <li class="px-4 py-2">
                        <p class="truncate text-sm text-ink">{{ $click->bioLink->title ?? 'Removed link' }}</p>
                        <p class="flex items-center gap-1.5 truncate text-xs text-muted">
                            @if ($click->country){!! country_flag($click->country) !!}@endif
                            <span class="truncate">{{ $click->bioLink->bioPage->title ?? '—' }} · {{ $click->created_at->diffForHumans(null, true) }}</span>
                        </p>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-muted">No clicks yet.</li>
                @endforelse
            </ul>
        </section>
    </div>

    @push('scripts')
    <script nonce="{{ Vite::cspNonce() }}">
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('clicksChart').getContext('2d');
            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)';
            const textColor = isDark ? '#94a3b8' : '#64748b';
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($chartLabels),
                    datasets: [{
                        label: 'Clicks',
                        data: @json($chartClicks),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.12)',
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#6366f1',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: isDark ? '#1e293b' : '#fff',
                            titleColor: isDark ? '#f1f5f9' : '#0f172a',
                            bodyColor: isDark ? '#94a3b8' : '#64748b',
                            borderColor: isDark ? '#334155' : '#e2e8f0',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: gridColor,
                            },
                            ticks: {
                                color: textColor,
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: gridColor,
                            },
                            ticks: {
                                color: textColor,
                                precision: 0
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
