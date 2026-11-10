@php use Illuminate\Support\Str; use Illuminate\Support\Facades\Storage; @endphp
<x-app-layout>
    <x-slot name="pageTitle">{{ __('Analytics') }}: /{{ $link->slug }}</x-slot>
    <x-page-header title="/{{ $link->slug }}" subtitle="Analytics for the last {{ $rangeInDays }} days">
        <x-slot name="actions">
            <a href="{{ route('links.index') }}" class="btn-ghost btn-sm">My links</a>
            <a href="{{ route('links.edit', $link) }}" class="btn-primary btn-sm">Edit link</a>
        </x-slot>
    </x-page-header>

    <x-flash />

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-4">
        <section class="glass-card flex items-center gap-4 lg:col-span-3">
            <div class="min-w-0 flex-1">
                <p class="eyebrow">Short link</p>
                <p class="mt-0.5 break-all text-base font-semibold text-ink">{{ $link->short_url }}</p>
                <p class="mt-0.5 truncate text-sm text-muted" title="{{ $link->target_url }}">→ {{ $link->target_url }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    {{-- The state a visitor meets, not only the on/off switch: an exhausted or expired link is not "Active". --}}
                    @if ($link->isExpired())
                        <span class="chip-red">{{ __('Expired') }}</span>
                    @elseif ($link->status !== 'active')
                        <span class="chip-gray">{{ __('Inactive') }}</span>
                    @elseif ($link->isLimitReached())
                        <span class="chip-red">{{ __('Limit reached') }}</span>
                    @elseif ($link->isNotStarted())
                        <span class="chip-gray" title="{{ $link->starts_at }} UTC">{{ __('Starts in :when', ['when' => $link->starts_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true)]) }}</span>
                    @else
                        <span class="chip-green">{{ __('Active') }}</span>
                    @endif
                    @if ($link->max_clicks && ! $link->isLimitReached())
                        <span class="chip-gray tabular-nums">{{ number_format($link->clicks) }}/{{ number_format($link->max_clicks) }}</span>
                    @endif
                    @if ($link->requiresPassword())
                        <span class="chip-blue">{{ __('Password') }}</span>
                    @endif
                    @if ($link->expires_at && ! $link->isExpired())
                        <span class="chip-amber" title="{{ $link->expires_at }} UTC">{{ __('Expires :when', ['when' => $link->expires_at->diffForHumans()]) }}</span>
                    @endif
                    @if ($link->isBroken())
                        <span class="chip-amber" title="{{ __('Since :date', ['date' => $link->broken_since->toDateString()]) }}{{ $link->last_status ? ' · HTTP '.$link->last_status : '' }}">{{ __('Destination not responding') }}</span>
                    @endif
                </div>
            </div>
        </section>
        <div class="stat">
            <p class="stat-label">Total clicks</p>
            <p class="stat-value">{{ number_format($link->clicks) }}</p>
            <p class="text-xs text-muted">{{ number_format($totalClicks) }} in {{ $rangeInDays }} days</p>
            <p class="mt-1 text-xs text-muted">{{ __(':count unique visitors', ['count' => number_format($insights['unique'])]) }}</p>
        </div>
    </div>

    {{-- The link's QR code: see it, download it, change its colours. Before, it only existed in the creation dialog. --}}
    @php
        $qrFg = $link->qr_fg_color ?: '#000000';
        $qrBg = $link->qr_bg_color ?: '#ffffff';
        $qrPresets = ['#000000', '#1e40af', '#dc2626', '#059669', '#7c3aed', '#db2777'];
    @endphp
    <section class="glass-card" x-data="{ qrOpen: {{ $errors->has('qr_fg_color') || $errors->has('qr_bg_color') ? 'true' : 'false' }} }" aria-labelledby="qr-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <div class="flex h-28 w-28 shrink-0 items-center justify-center rounded-xl border border-slate-500/15 p-1" style="background-color: {{ $qrBg }}">
                <img src="{{ route('links.qr.download', [$link, 'png']) }}?v={{ md5($qrFg.$qrBg.$link->slug.(string) $link->updated_at) }}" alt="{{ __('QR code for :url', ['url' => $link->short_url]) }}" width="104" height="104" class="h-full w-full object-contain" loading="lazy">
            </div>
            <div class="min-w-0 flex-1">
                <h2 id="qr-title" class="section-title">{{ __('QR code') }}</h2>
                <p class="mt-1 text-xs text-muted">{{ __('Opens :url. Scans are counted apart from direct clicks.', ['url' => $link->short_url]) }}</p>
                <p class="mt-1 text-xs text-muted">{{ __('The QR code holds the short address. Change the destination any time: the printed code keeps working.') }} <a href="{{ route('qr') }}#dynamic" class="underline">{{ __('How that works') }}</a></p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('links.qr.download', [$link, 'png']) }}" download class="btn-soft btn-sm">PNG</a>
                    <a href="{{ route('links.qr.download', [$link, 'jpg']) }}" download class="btn-soft btn-sm">JPG</a>
                    <a href="{{ route('links.qr.download', [$link, 'svg']) }}" download class="btn-soft btn-sm">SVG</a>
                    <button type="button" class="btn-ghost btn-sm" @click="qrOpen = !qrOpen" :aria-expanded="qrOpen ? 'true' : 'false'" aria-controls="qr-colours">{{ __('Change colours') }}</button>
                </div>
                <form id="qr-colours" x-show="qrOpen" x-cloak x-collapse method="POST" action="{{ route('links.qr.update', $link) }}" class="mt-4 space-y-3 border-t border-slate-500/10 pt-4" x-data="{ fg: @js(old('qr_fg_color', $qrFg)), bg: @js(old('qr_bg_color', $qrBg)) }">
                    @csrf
                    <div>
                        <span class="label">{{ __('Code colour') }}</span>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            @foreach ($qrPresets as $preset)
                                <button type="button" @click="fg = '{{ $preset }}'" :class="fg === '{{ $preset }}' ? 'ring-2 ring-indigo-500 ring-offset-2 ring-offset-white dark:ring-offset-slate-900' : ''" class="h-9 w-9 shrink-0 rounded-lg border border-slate-500/20 pointer-coarse:h-11 pointer-coarse:w-11" style="background-color: {{ $preset }}" aria-label="{{ __('Code colour :color', ['color' => $preset]) }}"></button>
                            @endforeach
                            <label class="flex items-center gap-2 text-xs text-muted">
                                <input type="color" x-model="fg" class="h-9 w-14 shrink-0 cursor-pointer rounded-lg border border-slate-500/20 bg-transparent" aria-label="{{ __('Custom code colour') }}">
                                <input type="text" name="qr_fg_color" x-model="fg" pattern="#[0-9a-fA-F]{6}" class="input w-24! font-mono" aria-label="{{ __('Code colour as hex') }}">
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('qr_fg_color')" class="mt-1" />
                    </div>
                    <div>
                        <span class="label">{{ __('Background colour') }}</span>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <input type="color" x-model="bg" class="h-9 w-14 shrink-0 cursor-pointer rounded-lg border border-slate-500/20 bg-transparent" aria-label="{{ __('Background colour') }}">
                            <input type="text" name="qr_bg_color" x-model="bg" pattern="#[0-9a-fA-F]{6}" class="input w-24! font-mono" aria-label="{{ __('Background colour as hex') }}">
                        </div>
                        <x-input-error :messages="$errors->get('qr_bg_color')" class="mt-1" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit" class="btn-primary btn-sm">{{ __('Save colours') }}</button>
                        <button type="button" class="btn-ghost btn-sm" @click="qrOpen = false">{{ __('Cancel') }}</button>
                        <p class="text-xs text-muted">{{ __('Codes already printed keep working; only new downloads change.') }}</p>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <x-ab-results :link="$link" :card="true" />

    <div class="grid grid-cols-1 items-start gap-3 lg:grid-cols-3">
        <section class="glass-card min-w-0 lg:col-span-2">
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
            @php
                $src = $insights['sources'];
                $sourceRows = array_sum($src) === 0 ? [] : [__('Direct clicks') => $src['direct'], __('QR scans') => $src['qr']] + ($src['other'] > 0 ? [__('NFC taps') => $src['other']] : []);
            @endphp
            <section class="glass-card">
                <h2 class="section-title">{{ __('QR scans vs direct clicks') }}</h2>
                <x-analytics.rank-list class="mt-3" color="bg-teal-500/70" :items="collect($sourceRows)->map(fn ($count, $label) => ['label' => $label, 'value' => (int) $count])->values()" :empty="__('No clicks in this range.')" />
            </section>
            @if ($insights['in_app_total'] > 0)
                <section class="glass-card">
                    <h2 class="section-title">{{ __('In-app browsers') }}</h2>
                    <x-analytics.rank-list class="mt-3" :limit="4" color="bg-fuchsia-500/70" :items="collect($insights['in_app'])->map(fn ($count, $label) => ['label' => $label, 'value' => (int) $count])->values()" />
                </section>
            @endif
        </div>
    </div>

    <section class="glass overflow-hidden rounded-2xl" x-data="clickLog()">
        <div class="flex flex-col gap-2 px-4 pb-2 pt-4 md:flex-row md:items-center">
            <div class="flex-1">
                <h2 class="section-title">Click log</h2>
                <p class="text-xs text-muted">Latest {{ $recentClicks->count() }} clicks</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <input x-model="searchQuery" @input="filterClicks" type="search" placeholder="Country, device, referrer" aria-label="Search clicks" class="input w-52!">
                <select aria-label="Filter by device" x-model="deviceFilter" @change="filterClicks" class="input w-auto!">
                    <option value="">All devices</option>
                    <option value="Mobile">Mobile</option>
                    <option value="Desktop">Desktop</option>
                </select>
                <select aria-label="Filter by connection type" x-model="proxyFilter" @change="filterClicks" class="input w-auto!">
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
