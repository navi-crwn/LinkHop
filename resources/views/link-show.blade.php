@php use Illuminate\Support\Str; use Illuminate\Support\Facades\Storage; @endphp
<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - /{{ $link->slug }} analytics</x-slot>
    <x-page-header title="/{{ $link->slug }}" subtitle="Analytics for the last {{ $rangeInDays }} days">
        <x-slot name="actions">
            <a href="{{ route('links.index') }}" class="btn-ghost btn-sm">My links</a>
            <a href="{{ route('links.edit', $link) }}" class="btn-primary btn-sm">Edit link</a>
        </x-slot>
    </x-page-header>

    <x-flash />

    <div class="grid gap-3 lg:grid-cols-4">
        <section class="glass-card flex items-center gap-4 lg:col-span-3">
            <div class="min-w-0 flex-1">
                <p class="eyebrow">Short link</p>
                <p class="mt-0.5 break-all text-base font-semibold text-ink">{{ $link->short_url }}</p>
                <p class="mt-0.5 truncate text-sm text-muted" title="{{ $link->target_url }}">→ {{ $link->target_url }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @if ($link->isExpired())
                        <span class="chip-red">Expired</span>
                    @else
                        <span class="{{ $link->status === 'active' ? 'chip-green' : 'chip-gray' }}">{{ ucfirst($link->status) }}</span>
                    @endif
                    @if ($link->requiresPassword())
                        <span class="chip-blue">Password</span>
                    @endif
                    @if ($link->expires_at && ! $link->isExpired())
                        <span class="chip-amber">Expires {{ $link->expires_at->diffForHumans() }}</span>
                    @endif
                </div>
            </div>
            @if ($link->qr_code_path)
                <img src="{{ Storage::disk('public')->url($link->qr_code_path) }}" alt="QR code" class="hidden h-20 w-20 shrink-0 rounded-xl bg-white p-1 sm:block">
            @endif
        </section>
        <div class="stat">
            <p class="stat-label">Total clicks</p>
            <p class="stat-value">{{ number_format($link->clicks) }}</p>
            <p class="text-xs text-muted">{{ number_format($totalClicks) }} in {{ $rangeInDays }} days</p>
        </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-3">
        <section class="glass-card lg:col-span-2">
            <h2 class="section-title">Daily clicks</h2>
            <div class="relative mt-3 h-52"><canvas id="clickChart"></canvas></div>
        </section>
        <div class="grid gap-3">
            <section class="glass-card">
                <h2 class="section-title">Top locations</h2>
                <x-analytics.rank-list class="mt-3" :limit="4" :items="$countries->map(fn ($c) => ['label' => country_name($c->country), 'value' => (int) $c->total, 'flag' => $c->country])" />
            </section>
            <section class="glass-card">
                <h2 class="section-title">Devices</h2>
                <x-analytics.rank-list class="mt-3" :limit="4" color="bg-sky-500/70" :items="collect($devices)->map(fn ($count, $device) => ['label' => $device, 'value' => (int) $count])->values()" />
            </section>
        </div>
    </div>

    <section class="glass overflow-hidden rounded-2xl" x-data="clickLog()">
        <div class="flex flex-col gap-2 px-4 pb-2 pt-4 md:flex-row md:items-center">
            <div class="flex-1">
                <h2 class="section-title">Click log</h2>
                <p class="text-xs text-muted">Latest {{ $recentClicks->count() }} clicks</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <input x-model="searchQuery" @input="filterClicks" type="search" placeholder="Country, device, referrer" aria-label="Search clicks" class="input w-52! py-1.5! text-xs">
                <select aria-label="Filter by device" x-model="deviceFilter" @change="filterClicks" class="input w-auto! py-1.5! text-xs">
                    <option value="">All devices</option>
                    <option value="Mobile">Mobile</option>
                    <option value="Desktop">Desktop</option>
                </select>
                <select aria-label="Filter by connection type" x-model="proxyFilter" @change="filterClicks" class="input w-auto! py-1.5! text-xs">
                    <option value="">All traffic</option>
                    <option value="clean">Clean IPs</option>
                    <option value="proxy">Proxy / VPN</option>
                </select>
                <button type="button" @click="resetFilters" class="btn-ghost btn-sm">Reset</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table-glass">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Location</th>
                        <th class="hidden sm:table-cell">Device</th>
                        <th class="hidden md:table-cell">Referrer</th>
                        <th class="text-right">Traffic</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="click in paginatedClicks" :key="click.id">
                        <tr>
                            <td class="whitespace-nowrap text-xs text-ink" x-text="click.time"></td>
                            <td class="text-xs text-ink [&_img]:inline [&_img]:rounded-xs" x-html="click.location"></td>
                            <td class="hidden text-xs text-muted sm:table-cell" x-text="click.device"></td>
                            <td class="hidden max-w-48 truncate text-xs text-muted md:table-cell" :title="click.referrer" x-text="click.referrer"></td>
                            <td class="text-right" x-html="click.proxy_badge"></td>
                        </tr>
                    </template>
                    <tr x-show="filteredClicks.length === 0">
                        <td colspan="5" class="py-8 text-center text-sm text-muted">No clicks match.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between border-t border-slate-500/10 px-4 py-2.5" x-show="filteredClicks.length > perPage">
            <p class="text-xs text-muted">
                <span x-text="(currentPage - 1) * perPage + 1"></span>–<span x-text="Math.min(currentPage * perPage, filteredClicks.length)"></span> of <span x-text="filteredClicks.length"></span>
            </p>
            <div class="flex gap-1">
                <button type="button" @click="currentPage--" :disabled="currentPage === 1" class="btn-ghost btn-sm disabled:opacity-40">Prev</button>
                <button type="button" @click="currentPage++" :disabled="currentPage * perPage >= filteredClicks.length" class="btn-ghost btn-sm disabled:opacity-40">Next</button>
            </div>
        </div>
    </section>

    <section class="glass-card space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="section-title">Notes</h2>
            <span class="text-xs text-muted">{{ $comments->count() }}</span>
        </div>
        @auth
            <form method="POST" action="{{ route('links.comments.store', $link) }}" class="flex flex-col gap-2 sm:flex-row sm:items-start">
                @csrf
                <label for="note-body" class="sr-only">Note</label>
                <textarea id="note-body" name="body" rows="1" class="input flex-1" placeholder="Add a note" required></textarea>
                <button class="btn-primary btn-sm shrink-0">Add note</button>
            </form>
        @endauth
        <ul class="max-h-56 space-y-1.5 overflow-y-auto">
            @forelse ($comments as $comment)
                <li class="glass-inset rounded-xl px-3 py-2">
                    <p class="text-sm text-ink">{{ $comment->body }}</p>
                    <p class="mt-0.5 text-[11px] text-muted">{{ $comment->user->name ?? 'You' }} · {{ $comment->created_at->diffForHumans() }}</p>
                </li>
            @empty
                <li class="text-xs text-muted">No notes yet.</li>
            @endforelse
        </ul>
    </section>

    @push('scripts')
        <script nonce="{{ Vite::cspNonce() }}">
            // Chart
            new Chart(document.getElementById('clickChart'), {
                type: 'line',
                data: {
                    labels: {!! json_encode(array_keys($dailyClicks)) !!},
                    datasets: [{
                        data: {!! json_encode(array_values($dailyClicks)) !!},
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.12)',
                        pointRadius: 0,
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
            // Click Log Alpine Component
            function clickLog() {
                return {
                    allClicks: {!! json_encode($recentClicks->map(function($click) {
                        $ua = $click->user_agent ? app(\App\Services\UserAgentService::class)->parse($click->user_agent) : null;
                        $location = $click->country ? (country_flag($click->country) . ' ' . e(country_name($click->country))) : '-';
                        $proxyBadge = $click->is_proxy ? '<span class="chip-red">Proxy</span>' : '<span class="chip-green">Clean</span>';
                        return [
                            'id' => $click->id,
                            'time' => $click->clicked_at ? $click->clicked_at->format('M d H:i') : $click->created_at->format('M d H:i'),
                            'location' => $location,
                            'device' => $ua['device'] ?? '-',
                            'referrer' => Str::limit($click->referer ?? '(direct)', 25),
                            'proxy_badge' => $proxyBadge,
                            'device_raw' => $ua['device'] ?? '',
                            'is_proxy' => $click->is_proxy,
                            'search_text' => country_name($click->country) . ' ' . ($ua['device'] ?? '') . ' ' . ($click->referer ?? ''),
                        ];
                    })) !!},
                    filteredClicks: [],
                    searchQuery: '',
                    deviceFilter: '',
                    proxyFilter: '',
                    currentPage: 1,
                    perPage: 15,
                    init() {
                        this.filteredClicks = this.allClicks;
                    },
                    filterClicks() {
                        this.filteredClicks = this.allClicks.filter(click => {
                            let match = true;
                            if (this.searchQuery) {
                                match = match && click.search_text.toLowerCase().includes(this.searchQuery.toLowerCase());
                            }
                            if (this.deviceFilter) {
                                match = match && click.device_raw === this.deviceFilter;
                            }
                            if (this.proxyFilter === 'clean') {
                                match = match && !click.is_proxy;
                            } else if (this.proxyFilter === 'proxy') {
                                match = match && click.is_proxy;
                            }
                            return match;
                        });
                        this.currentPage = 1;
                    },
                    resetFilters() {
                        this.searchQuery = '';
                        this.deviceFilter = '';
                        this.proxyFilter = '';
                        this.filterClicks();
                    },
                    get paginatedClicks() {
                        const start = (this.currentPage - 1) * this.perPage;
                        return this.filteredClicks.slice(start, start + this.perPage);
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>
