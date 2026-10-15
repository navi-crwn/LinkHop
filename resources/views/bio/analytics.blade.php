<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - {{ $bioPage->title }} analytics</x-slot>
    <x-page-header :title="$bioPage->title" subtitle="hel.ink/b/{{ $bioPage->slug }} · last {{ $days }} days">
        <x-slot name="actions">
            <nav class="glass-inset flex rounded-xl p-0.5" aria-label="Period">
                @foreach ([7, 30] as $range)
                    <a href="{{ route('bio.analytics', ['bioPage' => $bioPage, 'days' => $range]) }}" @if ($days === $range) aria-current="page" @endif
                       class="rounded-lg px-3 py-1 text-xs font-medium transition {{ $days === $range ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-muted hover:text-ink' }}">{{ $range }}d</a>
                @endforeach
            </nav>
            <a href="{{ route('bio.public.qr', $bioPage->slug) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm">QR code</a>
            <a href="{{ route('bio.edit', $bioPage) }}" class="btn-soft btn-sm">Edit</a>
            <a href="{{ route('bio.public.show', $bioPage->slug) }}" target="_blank" rel="noopener" class="btn-primary btn-sm">View live</a>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="stat"><p class="stat-label">Views</p><p class="stat-value">{{ number_format($stats['totalViews']) }}</p></div>
        <div class="stat"><p class="stat-label">Link clicks</p><p class="stat-value">{{ number_format($stats['totalClicks']) }}</p></div>
        <div class="stat"><p class="stat-label">Click rate</p><p class="stat-value">{{ $stats['ctr'] }}%</p></div>
        <div class="stat"><p class="stat-label">Active links</p><p class="stat-value">{{ number_format($stats['activeLinks']) }}</p></div>
    </div>

    <div class="grid gap-3 lg:grid-cols-3">
        <section class="glass-card lg:col-span-2">
            <h2 class="section-title">Clicks over time</h2>
            <div class="mt-3 h-56"><canvas id="viewsChart"></canvas></div>
        </section>
        <section class="glass-card">
            <h2 class="section-title">Devices</h2>
            <div class="mt-3 h-56"><canvas id="deviceChart"></canvas></div>
        </section>
    </div>

    <div class="grid items-start gap-3 lg:grid-cols-3">
        <section class="glass-card">
            <h2 class="section-title">Top links</h2>
            <x-analytics.rank-list class="mt-3" :items="$stats['topLinks']->map(fn ($l) => ['label' => trim(($l->icon ? $l->icon.' ' : '').$l->title), 'value' => (int) $l->click_count, 'sub' => $l->url])" empty="No link clicks yet." />
        </section>
        <section class="glass-card">
            <h2 class="section-title">Top countries</h2>
            <x-analytics.rank-list class="mt-3" color="bg-emerald-500/70" :items="$stats['topCountries']->map(fn ($c) => ['label' => country_name($c->country), 'value' => (int) $c->clicks, 'flag' => $c->country])" empty="No clicks in this period." />
        </section>
        <section class="glass overflow-hidden rounded-2xl">
            <h2 class="section-title px-4 pb-2 pt-4">Recent clicks</h2>
            <ul class="divide-glass max-h-96 overflow-y-auto">
                @forelse ($stats['recentClicks'] as $click)
                    <li class="px-4 py-2">
                        <p class="truncate text-sm text-ink">{{ $click->bioLink->icon ?? '' }} {{ $click->bioLink->title ?? 'Removed link' }}</p>
                        <p class="flex items-center gap-1.5 truncate text-xs text-muted">
                            @if ($click->country){!! country_flag($click->country) !!}@endif
                            <span class="truncate">{{ ucfirst($click->device ?? 'unknown') }} · {{ $click->browser ?? 'unknown' }} · {{ $click->clicked_at?->diffForHumans(null, true) }}</span>
                        </p>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-muted">No recent activity.</li>
                @endforelse
            </ul>
        </section>
    </div>

    @push('scripts')
        <script nonce="{{ Vite::cspNonce() }}">
            const viewsChartCtx = document.getElementById('viewsChart').getContext('2d');
            new Chart(viewsChartCtx, {
                type: 'line',
                data: {
                    labels: @json($stats['viewsChart']['labels']),
                    datasets: [{
                        label: 'Link clicks',
                        data: @json($stats['viewsChart']['data']),
                        borderColor: '#6366f1',
                        pointRadius: 0,
                        backgroundColor: 'rgba(99, 102, 241, 0.12)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            const deviceChartCtx = document.getElementById('deviceChart').getContext('2d');
            new Chart(deviceChartCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($stats['deviceChart']['labels']),
                    datasets: [{
                        data: @json($stats['deviceChart']['data']),
                        backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#0ea5e9'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        </script>
    @endpush
</x-app-layout>
