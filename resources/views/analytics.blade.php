<x-app-layout>
    <x-slot name="pageTitle">{{ __('Analytics') }}</x-slot>
    @php
        $topCountryCode = $countries->keys()->first();
        $topDevice = collect($devices)->sortDesc()->keys()->first();
        $domainBreakdown = [];
        foreach ($referers as $ref => $total) {
            $host = $ref === '(direct)' ? '(direct)' : parse_url($ref, PHP_URL_HOST);
            $domainBreakdown[$host ?: $ref] = ($domainBreakdown[$host ?: $ref] ?? 0) + $total;
        }
        arsort($domainBreakdown);
        $rows = fn ($data, $flagKey = null) => collect($data)->map(fn ($v, $k) => ['label' => $k, 'value' => (int) $v])->values();
        $tabBtn = 'tab';
        $tabOn = 'bg-slate-900/5 text-ink dark:bg-white/10';
        $tabOff = 'text-muted hover:text-ink';
        // The toolbar answers "how many clicks, from where, when" without scrolling: one row of filters, one KPI
        // card, the chart, and one card with tabs for every breakdown (the page header lives in the top bar).
        $filterCount = ($countryFilter !== 'all' ? 1 : 0) + ($deviceFilter !== 'all' ? 1 : 0);
    @endphp

    <div class="space-y-4">
        {{-- Filters. relative z-20: .glass has a backdrop-filter, which makes the form its own layer; without a z-index the
             cards after it are painted over the open link list. z-20 stays under the top bar (z-30) and the menus. --}}
        <form method="GET" x-ref="form" class="glass relative z-20 flex flex-wrap items-center gap-2 rounded-2xl p-2">
            <div class="relative w-full md:min-w-56 md:flex-1" x-data="{
                    open: false,
                    search: '',
                    selected: @js((string) $linkFilter),
                    selectedText: @js($linkFilter === 'all' ? __('All links') : '/'.($userLinks->firstWhere('id', $linkFilter)?->slug ?? '')),
                    links: @js($userLinks->map(fn ($l) => ['id' => $l->id, 'slug' => $l->slug, 'url' => \Illuminate\Support\Str::limit($l->target_url, 40)])),
                    get filteredLinks() {
                        if (!this.search) return this.links;
                        const s = this.search.toLowerCase();
                        return this.links.filter(l => l.slug.toLowerCase().includes(s) || l.url.toLowerCase().includes(s));
                    },
                    pick(id) { this.$refs.hiddenInput.value = id; this.$refs.form.submit(); }
                }" @click.outside="open = false" @keydown.escape="if (open) { open = false; $refs.toggle.focus(); }">
                <input type="hidden" name="link_id" x-ref="hiddenInput" :value="selected">
                <button type="button" x-ref="toggle" @click="open = !open" :aria-expanded="open.toString()" data-ctl="select" class="input flex items-center justify-between gap-2 text-left">
                    <span class="truncate" x-text="selectedText"></span>
                    <svg class="ml-2 h-4 w-4 shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open" x-transition x-cloak class="glass-strong glass-popover absolute z-50 mt-1 w-full min-w-[240px] rounded-xl p-1.5">
                    <input type="text" x-model="search" placeholder="{{ __('Search links…') }}" aria-label="{{ __('Search links') }}" class="input mb-1" @click.stop>
                    <div class="max-h-64 overflow-y-auto">
                        <button type="button" @click="pick('all')" data-ctl="option" class="option text-ink">{{ __('All links') }}</button>
                        <template x-for="link in filteredLinks" :key="link.id">
                            <button type="button" @click="pick(link.id)" data-ctl="option" class="option" :class="selected == link.id && 'bg-indigo-500/10'">
                                <div class="text-sm font-medium text-ink" x-text="'/' + link.slug"></div>
                                <div class="truncate text-xs text-muted" x-text="link.url"></div>
                            </button>
                        </template>
                        <p x-show="filteredLinks.length === 0" class="px-3 py-4 text-center text-sm text-muted">{{ __('No links found') }}</p>
                    </div>
                </div>
            </div>
            {{-- Time range: a segmented control on wide screens, a compact select on a phone. Both write the same field. --}}
            <div class="hidden items-center gap-0.5 rounded-xl bg-slate-900/5 p-0.5 dark:bg-white/10 md:flex" role="group" aria-label="{{ __('Time range') }}"
                 x-data="{ pick(hours) { const form = this.$el.closest('form'); form.querySelector('[name=hours]').value = hours; form.requestSubmit(); } }">
                @foreach ([6, 12, 24, 48, 72] as $option)
                    <button type="button" @click="pick({{ $option }})" data-ctl="tab" class="{{ $tabBtn }}" @class([$tabOn => $range == $option, $tabOff => $range != $option])>{{ $option }}h</button>
                @endforeach
            </div>
            <select name="hours" data-autosubmit class="input w-auto md:hidden" aria-label="{{ __('Time range') }}">
                @foreach ([6, 12, 24, 48, 72] as $option)
                    <option value="{{ $option }}" @selected($range == $option)>{{ __('Last :count hours', ['count' => $option]) }}</option>
                @endforeach
            </select>
            {{-- Location and device live in one popover, so the toolbar stays one row; the badge counts what is active. --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="if (open) { open = false; $refs.filterToggle.focus(); }">
                <button type="button" x-ref="filterToggle" @click="open = !open" :aria-expanded="open.toString()" data-ctl="select" class="input flex w-auto items-center justify-between gap-2 text-left">
                    <span class="text-sm">{{ __('Filter') }}</span>
                    @if ($filterCount > 0)
                        <span class="rounded-full bg-indigo-500/15 px-1.5 text-[11px] font-semibold text-indigo-600 dark:text-indigo-300">{{ $filterCount }}</span>
                    @endif
                    <svg class="h-4 w-4 shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open" x-transition x-cloak class="glass-strong glass-popover absolute right-0 z-50 mt-1 w-64 space-y-2 rounded-xl p-2">
                    <div>
                        <label class="label" for="analytics-country">{{ __('Location') }}</label>
                        <select id="analytics-country" name="country" data-autosubmit class="input" aria-label="{{ __('Location') }}">
                            <option value="all" @selected($countryFilter === 'all')>{{ __('All locations') }}</option>
                            @foreach ($availableCountries as $country)
                                <option value="{{ $country->country ?? 'unknown' }}" @selected(($country->country ?? 'unknown') === $countryFilter)>{{ $country->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="analytics-device">{{ __('Device') }}</label>
                        <select id="analytics-device" name="device" data-autosubmit class="input" aria-label="{{ __('Device') }}">
                            <option value="all" @selected($deviceFilter === 'all')>{{ __('All devices') }}</option>
                            @foreach ($availableDevices as $device)
                                <option value="{{ strtolower($device) }}" @selected(strtolower($device) === $deviceFilter)>{{ $device }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            @if ($linkFilter !== 'all' || $countryFilter !== 'all' || $deviceFilter !== 'all' || $range != 24)
                <a href="{{ route('analytics') }}" class="btn-ghost">{{ __('Reset') }}</a>
            @endif
        </form>

        {{-- Key metrics: one card, four numbers --}}
        <div class="glass grid grid-cols-2 rounded-2xl md:grid-cols-4">
            <div class="p-4">
                <p class="stat-label">{{ __('Clicks') }}</p>
                <p class="stat-value">{{ number_format($totalClicks) }}</p>
                @if ($previousClicks > 0)
                    @php $delta = (int) round(($totalClicks - $previousClicks) / $previousClicks * 100); @endphp
                    <p class="mt-0.5 text-xs {{ $delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">{{ $delta >= 0 ? '+' : '' }}{{ $delta }}% {{ __('against the period before') }}</p>
                @else
                    <p class="mt-0.5 text-xs text-muted" title="{{ __('Nothing before this period to compare with') }}">—</p>
                @endif
            </div>
            <div class="border-l border-slate-500/15 p-4">
                <p class="stat-label">{{ __('Unique visitors') }}</p>
                <p class="stat-value">{{ number_format($insights['unique']) }}</p>
                <p class="mt-0.5 text-xs text-muted" title="{{ __('Counted once per day') }}">{{ __('Counted once per day') }}</p>
            </div>
            <div class="border-t border-slate-500/15 p-4 md:border-t-0">
                <p class="stat-label">{{ __('Top country') }}</p>
                <div class="mt-0.5 flex items-center gap-2">
                    @if ($topCountryCode)<span class="[&_img]:rounded-xs">{!! country_flag($topCountryCode) !!}</span>@endif
                    <p class="stat-value truncate">{{ $topCountryCode ? country_name($topCountryCode) : '—' }}</p>
                </div>
                <p class="mt-0.5 text-xs text-muted">{{ __(':count countries', ['count' => number_format($countryCount)]) }}</p>
            </div>
            <div class="border-l border-t border-slate-500/15 p-4 md:border-t-0">
                <p class="stat-label">{{ __('Top device') }}</p>
                <p class="stat-value">{{ $topDevice ?? '—' }}
                    @if ($topDevice && $totalClicks)
                        <span class="text-xs font-normal text-muted">{{ round($devices[$topDevice] / $totalClicks * 100) }}%</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- Timeline --}}
        <section class="glass-card min-w-0">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="section-title">{{ __('Clicks over time') }}</h2>
                <span class="hidden text-[11px] text-muted sm:inline">{{ __('Scroll or pinch to zoom') }}</span>
            </div>
            <div class="relative h-36 sm:h-40"><canvas id="analyticsChart"></canvas></div>
        </section>

        {{-- Every breakdown in one card with tabs: one panel visible at a time, the active tab kept in the URL.
             The map is built the first time the Locations tab shows it (a Leaflet map in a hidden panel measures 0). --}}
        <section class="glass-card min-w-0" aria-labelledby="breakdown-title"
            x-data="{
                valid: ['locations', 'sources', 'technology', 'links', 'hours', 'networks'],
                get initial() {
                    const wanted = new URLSearchParams(location.hash.replace(/^#/, '')).get('tab');
                    return this.valid.includes(wanted) ? wanted : 'locations';
                },
                tab: 'locations',
                showMap: window.matchMedia('(min-width: 1024px)').matches,
                mapDone: false,
                setTab(name) {
                    this.tab = name;
                    history.replaceState(null, '', '#tab=' + name);
                    if (name === 'locations') { this.$nextTick(() => this.openMap()); }
                },
                openMap() {
                    if (!this.showMap || this.mapDone) return;
                    this.mapDone = true;
                    window.initAnalyticsMap?.();
                },
                toggleMap() {
                    this.showMap = !this.showMap;
                    if (this.showMap) { this.$nextTick(() => { this.openMap(); window.analyticsMapResize?.(); }); }
                },
            }"
            x-init="tab = initial; if (tab === 'locations') { $nextTick(() => openMap()); }">
            <h2 id="breakdown-title" class="sr-only">{{ __('Breakdown') }}</h2>
            <div class="mb-3 -mx-1 overflow-x-auto px-1">
                <div class="flex min-w-max gap-0.5" role="tablist" aria-label="{{ __('Breakdown') }}">
                    @foreach (['locations' => __('Locations'), 'sources' => __('Sources'), 'technology' => __('Technology'), 'links' => __('Links'), 'hours' => __('Busiest hours'), 'networks' => __('Networks')] as $key => $label)
                        <button type="button" role="tab" :aria-selected="(tab === '{{ $key }}').toString()" @click="setTab('{{ $key }}')" data-ctl="tab" class="{{ $tabBtn }}" :class="tab === '{{ $key }}' ? '{{ $tabOn }}' : '{{ $tabOff }}'">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Locations: the ranked list first on a phone, the map beside it on wide screens (toggle below lg). --}}
            <div x-show="tab === 'locations'">
                <div class="grid gap-4 lg:grid-cols-5">
                    <div class="min-w-0 order-2 lg:order-1 lg:col-span-3">
                        <button type="button" @click="toggleMap()" class="btn-ghost btn-dense mb-2 lg:hidden" x-text="showMap ? @js(__('Hide map')) : @js(__('Show map'))">{{ __('Show map') }}</button>
                        <div x-show="showMap" x-cloak class="relative">
                            <div id="world-map" class="h-64 w-full sm:h-80"></div>
                            <div class="glass-strong pointer-events-none absolute bottom-3 left-3 z-400 rounded-xl px-3 py-2">
                                <p id="map-country-name" class="text-xs font-semibold text-ink">{{ __('Tap a country') }}</p>
                                <p id="map-country-clicks" class="text-[11px] text-muted">—</p>
                            </div>
                        </div>
                    </div>
                    <div class="min-w-0 order-1 lg:order-2 lg:col-span-2" x-data="{ tab: 'countries' }">
                        <div class="mb-3 flex items-center justify-end gap-0.5">
                            @foreach (['countries' => __('Countries'), 'cities' => __('Cities'), 'regions' => __('Regions')] as $key => $label)
                                <button type="button" @click="tab = '{{ $key }}'" data-ctl="tab" class="{{ $tabBtn }}" :class="tab === '{{ $key }}' ? '{{ $tabOn }}' : '{{ $tabOff }}'">{{ $label }}</button>
                            @endforeach
                        </div>
                        <div x-show="tab === 'countries'">
                            <x-analytics.rank-list :limit="5" :items="collect($countries)->take(50)->map(fn ($v, $k) => ['label' => country_name($k), 'value' => (int) $v, 'flag' => $k])" :empty="__('No location data yet.')" />
                        </div>
                        <div x-show="tab === 'cities'" x-cloak>
                            <x-analytics.rank-list :limit="5" :items="collect($cities)->take(50)->map(fn ($c) => ['label' => $c['city'], 'sub' => country_name($c['country']), 'value' => (int) $c['total'], 'flag' => $c['country']])" :empty="__('No city data yet.')" />
                        </div>
                        <div x-show="tab === 'regions'" x-cloak>
                            <x-analytics.rank-list :limit="5" :items="collect($regions)->take(50)->map(fn ($r) => ['label' => $r['region'], 'sub' => country_name($r['country']), 'value' => (int) $r['total'], 'flag' => $r['country']])" :empty="__('No region data yet.')" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sources: referrers and the QR/direct split --}}
            <div x-show="tab === 'sources'" x-cloak>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="min-w-0" x-data="{ tab: 'domains' }">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h3 class="section-title">{{ __('Referrers') }}</h3>
                            <div class="flex gap-0.5">
                                <button type="button" @click="tab = 'domains'" data-ctl="tab" class="{{ $tabBtn }}" :class="tab === 'domains' ? '{{ $tabOn }}' : '{{ $tabOff }}'">{{ __('Domains') }}</button>
                                <button type="button" @click="tab = 'urls'" data-ctl="tab" class="{{ $tabBtn }}" :class="tab === 'urls' ? '{{ $tabOn }}' : '{{ $tabOff }}'">{{ __('URLs') }}</button>
                            </div>
                        </div>
                        <div x-show="tab === 'domains'">
                            <x-analytics.rank-list :limit="5" :items="$rows($domainBreakdown)" color="bg-sky-500/70" :empty="__('No referrers yet.')" />
                        </div>
                        <div x-show="tab === 'urls'" x-cloak>
                            <x-analytics.rank-list :limit="5" :items="$rows($referers)" color="bg-sky-500/70" :empty="__('No referrers yet.')" />
                        </div>
                    </div>
                    <div class="min-w-0">
                        <h3 class="section-title">{{ __('QR scans vs direct clicks') }}</h3>
                        <p class="mb-2 mt-0.5 text-xs text-muted">{{ __('A scan is a visit through the QR code made by LinkHop. Codes printed before this feature count as direct clicks until downloaded again.') }}</p>
                        @php
                            $src = $insights['sources'];
                            $sourceRows = array_sum($src) === 0 ? [] : [__('Direct clicks') => $src['direct'], __('QR scans') => $src['qr']] + ($src['other'] > 0 ? [__('NFC taps') => $src['other']] : []);
                        @endphp
                        <x-analytics.rank-list :limit="5" :items="$rows($sourceRows)" color="bg-teal-500/70" :empty="__('No clicks in this range.')" />
                    </div>
                </div>
            </div>

            {{-- Technology: devices, browsers, systems and in-app browsers --}}
            <div x-show="tab === 'technology'" x-cloak x-data="{ tab: 'devices' }">
                <div class="mb-3 flex flex-wrap items-center justify-end gap-0.5">
                    @foreach (['devices' => __('Devices'), 'browsers' => __('Browsers'), 'os' => __('OS'), 'inapp' => __('In-app browsers')] as $key => $label)
                        <button type="button" @click="tab = '{{ $key }}'" data-ctl="tab" class="{{ $tabBtn }}" :class="tab === '{{ $key }}' ? '{{ $tabOn }}' : '{{ $tabOff }}'">{{ $label }}</button>
                    @endforeach
                </div>
                <div x-show="tab === 'devices'"><x-analytics.rank-list :limit="5" :items="$rows($devices)" color="bg-emerald-500/70" :empty="__('No device data.')" /></div>
                <div x-show="tab === 'browsers'" x-cloak><x-analytics.rank-list :limit="5" :items="$rows($browserStats)" color="bg-violet-500/70" :empty="__('No browser data.')" /></div>
                <div x-show="tab === 'os'" x-cloak><x-analytics.rank-list :limit="5" :items="$rows($osStats)" color="bg-amber-500/70" :empty="__('No OS data.')" /></div>
                <div x-show="tab === 'inapp'" x-cloak>
                    <p class="mb-2 text-xs text-muted">{{ __('Visits opened inside another app, such as Instagram or WeChat: :count of :total.', ['count' => number_format($insights['in_app_total']), 'total' => number_format($totalClicks)]) }}</p>
                    <x-analytics.rank-list :limit="5" :items="$rows($insights['in_app'])" color="bg-fuchsia-500/70" :empty="__('No in-app browser visits.')" />
                </div>
            </div>

            {{-- Links: the account's own links, their destinations and the ended ones --}}
            <div x-show="tab === 'links'" x-cloak x-data="{ tab: 'links' }">
                <div class="mb-3 flex items-center justify-end gap-0.5">
                    <button type="button" @click="tab = 'links'" data-ctl="tab" class="{{ $tabBtn }}" :class="tab === 'links' ? '{{ $tabOn }}' : '{{ $tabOff }}'">{{ __('Links') }}</button>
                    <button type="button" @click="tab = 'dest'" data-ctl="tab" class="{{ $tabBtn }}" :class="tab === 'dest' ? '{{ $tabOn }}' : '{{ $tabOff }}'">{{ __('Destinations') }}</button>
                    <button type="button" @click="tab = 'ended'" data-ctl="tab" class="{{ $tabBtn }}" :class="tab === 'ended' ? '{{ $tabOn }}' : '{{ $tabOff }}'">{{ __('Expired') }}</button>
                </div>
                <div x-show="tab === 'links'">
                    <x-analytics.rank-list :limit="5" :items="collect($topLinks)->filter(fn ($r) => $r->link)->map(fn ($r) => ['label' => '/'.$r->link->slug, 'sub' => $r->link->target_url, 'value' => (int) $r->total, 'href' => route('links.show', $r->link)])" :empty="__('No clicks in this range.')" />
                </div>
                <div x-show="tab === 'dest'" x-cloak>
                    <x-analytics.rank-list :limit="5" :items="$rows($topDestinations)" :empty="__('No destination data.')" />
                </div>
                <div x-show="tab === 'ended'" x-cloak>
                    <p class="mb-2 text-xs text-muted">{{ __('Expired or disabled links that still got clicks in this range.') }}</p>
                    <x-analytics.rank-list :limit="5" :items="collect($endedLinks)->filter(fn ($r) => $r->link)->map(fn ($r) => ['label' => '/'.$r->link->slug, 'sub' => $r->link->target_url, 'value' => (int) $r->total, 'href' => route('links.show', $r->link)])" color="bg-slate-400/60" :empty="__('No clicks on expired links.')" />
                </div>
            </div>

            {{-- When people click: weekday by hour over the last 30 days, with the filters above.
                 Fixed-size cells (not full-width squares), so a phone never scrolls sideways. --}}
            <div x-show="tab === 'hours'" x-cloak>
                @php $heatMax = max(1, ...array_map('max', $heatmap)); $days = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')]; @endphp
                <p class="mb-3 text-[11px] text-muted">{{ __('Last 30 days, in UTC') }}</p>
                <div class="heat-grid" role="img" aria-label="{{ __('Clicks by weekday and hour') }}">
                    @foreach ($heatmap as $d => $row)
                        <span class="self-center text-[10px] text-muted">{{ $days[$d] }}</span>
                        @foreach ($row as $h => $n)
                            <span class="heat-cell" style="background: rgb(99 102 241 / {{ $n ? round(0.15 + 0.85 * $n / $heatMax, 2) : 0.06 }})" title="{{ $days[$d] }} {{ sprintf('%02d:00', $h) }}: {{ $n }}"></span>
                        @endforeach
                    @endforeach
                    <span></span>
                    @foreach (range(0, 23) as $h)
                        <span class="text-center text-[9px] text-muted">{{ $h % 6 === 0 ? $h : '' }}</span>
                    @endforeach
                </div>
            </div>

            {{-- Networks: internet providers --}}
            <div x-show="tab === 'networks'" x-cloak>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="section-title">{{ __('Internet providers') }}</h3>
                    @if (($proxyStats['total'] ?? 0) > 0)
                        <span class="chip-amber" title="{{ __('Clicks from VPN / proxy / hosting networks') }}">{{ number_format($proxyStats['total']) }} {{ __('via proxy/VPN') }}</span>
                    @endif
                </div>
                <x-analytics.rank-list :limit="5" :items="collect($isps)->take(20)->map(fn ($i) => ['label' => ((object) $i)->isp, 'value' => (int) ((object) $i)->total])" color="bg-rose-500/60" :empty="__('No network data.')" />
            </div>
        </section>

        {{-- Visitor IPs --}}
        <details class="glass group rounded-2xl">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3">
                <span class="section-title">{{ __('Recent visitors') }} <span class="font-normal text-muted">({{ $recentIps->count() }})</span></span>
                <svg class="h-4 w-4 text-muted transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
            </summary>
            <div class="overflow-x-auto pb-2">
                <table class="table-glass">
                    <thead><tr><th>{{ __('Country') }}</th><th class="text-right">{{ __('When') }}</th></tr></thead>
                    <tbody>
                        @forelse ($recentIps as $hit)
                            <tr>
                                <td class="text-ink"><span class="inline-flex items-center gap-2 [&_img]:rounded-xs">{!! country_flag($hit->country) !!} {{ country_name($hit->country) }}</span></td>
                                <td class="text-right text-xs text-muted">{{ \Carbon\Carbon::parse($hit->created_at)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-sm text-muted">{{ __('No visitors for this filter.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    </div>

    @push('scripts')
        <style>
            .leaflet-container { background: transparent; font: inherit; }
            .leaflet-popup-content-wrapper { padding: 4px 6px; border-radius: 10px; }
            .leaflet-popup-content { margin: 0; }
            /* Busiest-hours heatmap: fixed cells so the grid never needs a sideways scroll on a phone. */
            .heat-grid { display: grid; gap: 2px; grid-template-columns: 1.75rem repeat(24, 10px); justify-content: start; }
            .heat-grid .heat-cell { width: 10px; height: 10px; border-radius: 3px; display: block; }
            @media (min-width: 640px) {
                .heat-grid { gap: 3px; grid-template-columns: 2.5rem repeat(24, 16px); }
                .heat-grid .heat-cell { width: 16px; height: 16px; }
            }
        </style>
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                const dark = () => document.documentElement.classList.contains('dark');
                const labels = @json(array_values($timeline->keys()->toArray()));
                const dataset = @json(array_values($timeline->values()->toArray()));
                const clicksWord = @js(__('clicks'));
                const ctx = document.getElementById('analyticsChart');
                const grad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 260);
                grad.addColorStop(0, 'rgba(99,102,241,0.35)');
                grad.addColorStop(1, 'rgba(99,102,241,0)');
                const chart = new Chart(ctx, {
                    type: 'line',
                    data: { labels, datasets: [{ label: @js(__('Clicks')), data: dataset, borderColor: '#6366f1', backgroundColor: grad, fill: true, tension: 0.35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, pointBackgroundColor: '#6366f1' }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: { displayColors: false, padding: 10, callbacks: { label: c => c.parsed.y + ' ' + clicksWord } },
                            zoom: { zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'x' }, pan: { enabled: true, mode: 'x' } }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 }, border: { display: false } },
                            x: { ticks: { maxRotation: 0, autoSkipPadding: 12 }, grid: { display: false }, border: { display: false } }
                        }
                    }
                });
                const applyTheme = () => {
                    const tick = dark() ? '#94a3b8' : '#64748b';
                    chart.options.scales.x.ticks.color = tick;
                    chart.options.scales.y.ticks.color = tick;
                    chart.options.scales.y.grid = { color: dark() ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.06)' };
                    chart.update('none');
                };
                applyTheme();
                window.addEventListener('theme-changed', applyTheme);
            })();
            // Leaflet Choropleth Map, built the first time the Locations tab shows it (a map in a hidden panel measures 0).
            const mapData = {!! json_encode($mapData) !!};
            const maxClicks = Math.max(...Object.values(mapData), 1);
            const iso3To2 = {"ATG":"AG","BTN":"BT","ITA":"IT","TUV":"TV","AIA":"AI","AUS":"AU","BLZ":"BZ","VUT":"VU","BLR":"BY","MUS":"MU","LAO":"LA","SEN":"SN","TUR":"TR","BOL":"BO","LKA":"LK","NFK":"NF","CHN":"CN","BES":"BQ","GGY":"GG","SDN":"SD","MYT":"YT","BLM":"BL","VAT":"VA","TCA":"TC","CUW":"CW","BWA":"BW","BEN":"BJ","LTU":"LT","MSR":"MS","VGB":"VG","BDI":"BI","UMI":"UM","IRL":"IE","SLB":"SB","BMU":"BM","FIN":"FI","PER":"PE","BGD":"BD","DNK":"DK","VCT":"VC","DOM":"DO","MDA":"MD","BGR":"BG","CRI":"CR","NAM":"NA","SJM":"SJ","LUX":"LU","RUS":"RU","ARE":"AE","SXM":"SX","BHS":"BS","JPN":"JP","NGA":"NG","GHA":"GH","SLE":"SL","SPM":"PM","ALB":"AL","TKL":"TK","SHN":"SH","TON":"TO","TKM":"TM","DJI":"DJ","CAF":"CF","LBN":"LB","LVA":"LV","CCK":"CC","GMB":"GM","HND":"HN","NIU":"NU","MRT":"MR","UNK":"XK","WLF":"WF","SGS":"GS","PYF":"PF","TGO":"TG","BEL":"BE","ZMB":"ZM","CYM":"KY","PCN":"PN","COK":"CK","MDG":"MG","MNE":"ME","KOR":"KR","ETH":"ET","MNG":"MN","SVK":"SK","CUB":"CU","ATA":"AQ","GTM":"GT","GUF":"GF","NOR":"NO","GRD":"GD","REU":"RE","CHL":"CL","COL":"CO","SAU":"SA","ISR":"IL","DEU":"DE","NZL":"NZ","GRL":"GL","KGZ":"KG","SLV":"SV","FRO":"FO","PLW":"PW","MLT":"MT","SYR":"SY","TLS":"TL","HRV":"HR","PNG":"PG","NLD":"NL","LBR":"LR","SOM":"SO","VEN":"VE","HTI":"HT","DZA":"DZ","MNP":"MP","MAF":"MF","HMD":"HM","ABW":"AW","EGY":"EG","MWI":"MW","GNQ":"GQ","VIR":"VI","ECU":"EC","UZB":"UZ","GAB":"GA","SSD":"SS","IRN":"IR","KAZ":"KZ","NIC":"NI","ISL":"IS","SVN":"SI","GLP":"GP","CMR":"CM","ARG":"AR","AZE":"AZ","UGA":"UG","NER":"NE","CXR":"CX","MMR":"MM","POL":"PL","JOR":"JO","HKG":"HK","COD":"CD","ERI":"ER","KIR":"KI","MHL":"MH","BFA":"BF","ZWE":"ZW","KEN":"KE","COM":"KM","GIB":"GI","BRN":"BN","SWE":"SE","LSO":"LS","IMN":"IM","FSM":"FM","TZA":"TZ","CPV":"CV","AFG":"AF","AND":"AD","GRC":"GR","VNM":"VN","ATF":"TF","IRQ":"IQ","LBY":"LY","PRT":"PT","PAK":"PK","MDV":"MV","MAR":"MA","BIH":"BA","WSM":"WS","PSE":"PS","OMN":"OM","BHR":"BH","USA":"US","PRI":"PR","IOT":"IO","JEY":"JE","MKD":"MK","TUN":"TN","TTO":"TT","EST":"EE","SGP":"SG","PAN":"PA","CHE":"CH","URY":"UY","TJK":"TJ","TWN":"TW","ZAF":"ZA","LIE":"LI","BRA":"BR","ARM":"AM","GEO":"GE","ALA":"AX","QAT":"QA","DMA":"DM","UKR":"UA","GIN":"GN","MAC":"MO","ESH":"EH","CZE":"CZ","AUT":"AT","KNA":"KN","LCA":"LC","YEM":"YE","RWA":"RW","MCO":"MC","STP":"ST","COG":"CG","PRY":"PY","BVT":"BV","MOZ":"MZ","FRA":"FR","SWZ":"SZ","BRB":"BB","ESP":"ES","THA":"TH","GNB":"GW","AGO":"AO","IND":"IN","MTQ":"MQ","NCL":"NC","SYC":"SC","FLK":"FK","GBR":"GB","FJI":"FJ","SMR":"SM","MLI":"ML","CAN":"CA","JAM":"JM","NRU":"NR","IDN":"ID","GUM":"GU","CIV":"CI","KWT":"KW","PHL":"PH","GUY":"GY","HUN":"HU","MEX":"MX","PRK":"KP","ROU":"RO","SUR":"SR","ASM":"AS","NPL":"NP","TCD":"TD","SRB":"RS","KHM":"KH","MYS":"MY","CYP":"CY"};
            const normalizeCountryCode = (code) => {
                if (!code) return '';
                const upper = String(code).toUpperCase();
                if (upper.length === 2) return upper;
                return iso3To2[upper] || upper;
            };
            const countryLayers = {};
            const clicksLabel = @js(__(':count clicks'));
            const noClicksYet = @js(__('No clicks yet'));
            window.focusCountry = (countryCode) => {
                if (!map) return;
                const normalized = normalizeCountryCode(countryCode);
                if (countryLayers[normalized] && countryLayers[normalized].getBounds) {
                    map.fitBounds(countryLayers[normalized].getBounds());
                    countryLayers[normalized].openPopup();
                    const clicks = mapData[normalized] || 0;
                    const name = countryLayers[normalized].feature?.properties?.name || normalized;
                    setSelectedCountry(name, clicks);
                }
            };
            const setSelectedCountry = (name, clicks) => {
                document.getElementById('map-country-name').textContent = name;
                document.getElementById('map-country-clicks').textContent = clicks ? clicksLabel.replace(':count', clicks.toLocaleString()) : noClicksYet;
            };
            let map;
            window.analyticsMapResize = () => { try { map?.invalidateSize(); } catch (e) {} };
            window.initAnalyticsMap = function () {
                if (map) { window.analyticsMapResize(); return; }
                try {
                    map = L.map('world-map', {
                        center: [20, 0],
                        zoom: 2,
                        minZoom: 1,
                        maxZoom: 6,
                        zoomControl: true,
                        scrollWheelZoom: false,
                        worldCopyJump: true
                    });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        noWrap: false
                    }).addTo(map);
                    fetch(@js(asset('vendor/geo/countries.geo.json')))
                        .then(response => response.json())
                        .then(geoData => {
                            function getColor(countryCode) {
                                const code = normalizeCountryCode(countryCode);
                                const clicks = mapData[code] || 0;
                                if (clicks === 0) return '#e2e8f0';
                                const intensity = clicks / maxClicks;
                                if (intensity > 0.7) return '#1d4ed8';
                                if (intensity > 0.4) return '#2563eb';
                                if (intensity > 0.2) return '#60a5fa';
                                return '#bfdbfe';
                            }
                            function style(feature) {
                                return {
                                    fillColor: getColor(feature.id ? feature.id.toUpperCase() : ''),
                                    weight: 1,
                                    opacity: 1,
                                    color: '#cbd5e1',
                                    fillOpacity: 0.8
                                };
                            }
                            function highlightFeature(e) {
                                const layer = e.target;
                                layer.setStyle({ weight: 2, color: '#475569', fillOpacity: 0.95 });
                                if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                                    layer.bringToFront();
                                }
                            }
                            function resetHighlight(e) {
                                geojson.resetStyle(e.target);
                            }
                            function onEachFeature(feature, layer) {
                                const countryCode = normalizeCountryCode(feature.id || feature.properties?.iso_a2);
                                const clicks = mapData[countryCode] || 0;
                                const countryName = feature.properties.name;
                                countryLayers[countryCode] = layer;
                                layer.bindPopup(`
                                    <div class="text-center p-1" style="width: 110px;">
                                        <p class="font-bold text-sm">${countryName}</p>
                                        <p class="text-xs text-slate-600">${clicksLabel.replace(':count', clicks.toLocaleString())}</p>
                                    </div>
                                `);
                                layer.on({
                                    mouseover: highlightFeature,
                                    mouseout: resetHighlight,
                                    click: function(e) {
                                        setSelectedCountry(countryName, clicks);
                                    }
                                });
                            }
                            const geojson = L.geoJSON(geoData, {
                                style: style,
                                onEachFeature: onEachFeature
                            }).addTo(map);
                            window.analyticsMapResize();
                        })
                        .catch(error => {
                            console.error('❌ Map error:', error);
                            document.getElementById('world-map').innerHTML =
                                '<div class="flex h-full items-center justify-center text-sm text-muted"><p>@js(__('Map unavailable right now'))</p></div>';
                        });
                } catch (error) {
                    console.error('❌ Map init error:', error);
                }
            };
        </script>
    @endpush
</x-app-layout>
