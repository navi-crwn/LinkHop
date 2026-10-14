<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Analytics</x-slot>
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
        $tabBtn = 'rounded-lg px-2 py-1 text-xs font-medium transition';
        $tabOn = 'bg-slate-900/5 text-ink dark:bg-white/10';
        $tabOff = 'text-muted hover:text-ink';
    @endphp

    <div class="space-y-4">
        <x-page-header title="Analytics" subtitle="Last {{ $range }} hours{{ $linkFilter !== 'all' ? ' · /'.($userLinks->firstWhere('id', $linkFilter)?->slug) : '' }}" />

        {{-- Filters --}}
        <form method="GET" x-ref="form" class="glass grid grid-cols-2 gap-2 rounded-2xl p-2 md:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto]">
            <div class="relative col-span-2 md:col-span-1" x-data="{
                    open: false,
                    search: '',
                    selected: @js((string) $linkFilter),
                    selectedText: @js($linkFilter === 'all' ? 'All links' : '/'.($userLinks->firstWhere('id', $linkFilter)?->slug ?? '')),
                    links: @js($userLinks->map(fn ($l) => ['id' => $l->id, 'slug' => $l->slug, 'url' => \Illuminate\Support\Str::limit($l->target_url, 40)])),
                    get filteredLinks() {
                        if (!this.search) return this.links;
                        const s = this.search.toLowerCase();
                        return this.links.filter(l => l.slug.toLowerCase().includes(s) || l.url.toLowerCase().includes(s));
                    },
                    pick(id) { this.$refs.hiddenInput.value = id; this.$refs.form.submit(); }
                }" @click.outside="open = false">
                <input type="hidden" name="link_id" x-ref="hiddenInput" :value="selected">
                <button type="button" @click="open = !open" class="input flex items-center justify-between text-left">
                    <span class="truncate" x-text="selectedText"></span>
                    <svg class="ml-2 h-4 w-4 shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open" x-transition x-cloak class="glass-strong absolute z-50 mt-1 w-full min-w-[240px] rounded-xl p-1.5">
                    <input type="text" x-model="search" placeholder="Search links…" class="input mb-1" @click.stop>
                    <div class="max-h-64 overflow-y-auto">
                        <button type="button" @click="pick('all')" class="w-full rounded-lg px-3 py-2 text-left text-sm text-ink hover:bg-slate-900/5 dark:hover:bg-white/10">All links</button>
                        <template x-for="link in filteredLinks" :key="link.id">
                            <button type="button" @click="pick(link.id)" class="w-full rounded-lg px-3 py-2 text-left hover:bg-slate-900/5 dark:hover:bg-white/10" :class="selected == link.id && 'bg-indigo-500/10'">
                                <div class="text-sm font-medium text-ink" x-text="'/' + link.slug"></div>
                                <div class="truncate text-xs text-muted" x-text="link.url"></div>
                            </button>
                        </template>
                        <p x-show="filteredLinks.length === 0" class="px-3 py-4 text-center text-sm text-muted">No links found</p>
                    </div>
                </div>
            </div>
            <select name="hours" data-autosubmit class="input" aria-label="Time range">
                @foreach ([6, 12, 24, 48, 72] as $option)
                    <option value="{{ $option }}" @selected($range == $option)>Last {{ $option }}h</option>
                @endforeach
            </select>
            <select name="country" data-autosubmit class="input" aria-label="Location">
                <option value="all" @selected($countryFilter === 'all')>All locations</option>
                @foreach ($availableCountries as $country)
                    <option value="{{ $country->country ?? 'unknown' }}" @selected(($country->country ?? 'unknown') === $countryFilter)>{{ $country->label }}</option>
                @endforeach
            </select>
            <select name="device" data-autosubmit class="input" aria-label="Device">
                <option value="all" @selected($deviceFilter === 'all')>All devices</option>
                @foreach ($availableDevices as $device)
                    <option value="{{ strtolower($device) }}" @selected(strtolower($device) === $deviceFilter)>{{ $device }}</option>
                @endforeach
            </select>
            <a href="{{ route('analytics') }}" class="btn-ghost justify-center">Reset</a>
        </form>

        {{-- Key metrics --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="stat">
                <p class="stat-label">Clicks</p>
                <p class="stat-value">{{ number_format($totalClicks) }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Top country</p>
                <div class="mt-0.5 flex items-center gap-2">
                    @if ($topCountryCode)<span class="[&_img]:rounded-sm">{!! country_flag($topCountryCode) !!}</span>@endif
                    <p class="stat-value truncate">{{ $topCountryCode ? country_name($topCountryCode) : '—' }}</p>
                </div>
            </div>
            <div class="stat">
                <p class="stat-label">Countries</p>
                <p class="stat-value">{{ number_format($countryCount) }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Top device</p>
                <p class="stat-value">{{ $topDevice ?? '—' }}
                    @if ($topDevice && $totalClicks)
                        <span class="text-xs font-normal text-muted">{{ round($devices[$topDevice] / $totalClicks * 100) }}%</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- Timeline --}}
        <section class="glass-card">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="section-title">Clicks over time</h2>
                <span class="text-[11px] text-muted">scroll / pinch to zoom</span>
            </div>
            <div class="relative h-56 sm:h-64"><canvas id="analyticsChart"></canvas></div>
        </section>

        {{-- Geography: map + one ranked list (countries / cities / regions) --}}
        <section class="glass grid overflow-hidden rounded-2xl lg:grid-cols-5">
            <div class="relative lg:col-span-3">
                <div id="world-map" class="h-64 w-full sm:h-80 lg:h-full lg:min-h-[22rem]"></div>
                <div class="glass-strong pointer-events-none absolute bottom-3 left-3 z-[400] rounded-xl px-3 py-2">
                    <p id="map-country-name" class="text-xs font-semibold text-ink">Tap a country</p>
                    <p id="map-country-clicks" class="text-[11px] text-muted">—</p>
                </div>
            </div>
            <div class="p-4 lg:col-span-2" x-data="{ tab: 'countries' }">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="section-title">Locations</h2>
                    <div class="flex gap-0.5">
                        @foreach (['countries' => 'Countries', 'cities' => 'Cities', 'regions' => 'Regions'] as $key => $label)
                            <button type="button" @click="tab = '{{ $key }}'" class="{{ $tabBtn }}" :class="tab === '{{ $key }}' ? '{{ $tabOn }}' : '{{ $tabOff }}'">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div x-show="tab === 'countries'">
                    <x-analytics.rank-list :limit="8" :items="collect($countries)->take(50)->map(fn ($v, $k) => ['label' => country_name($k), 'value' => (int) $v, 'flag' => $k])" empty="No location data yet." />
                </div>
                <div x-show="tab === 'cities'" x-cloak>
                    <x-analytics.rank-list :limit="8" :items="collect($cities)->take(50)->map(fn ($c) => ['label' => $c['city'], 'sub' => country_name($c['country']), 'value' => (int) $c['total'], 'flag' => $c['country']])" empty="No city data yet." />
                </div>
                <div x-show="tab === 'regions'" x-cloak>
                    <x-analytics.rank-list :limit="8" :items="collect($regions)->take(50)->map(fn ($r) => ['label' => $r['region'], 'sub' => country_name($r['country']), 'value' => (int) $r['total'], 'flag' => $r['country']])" empty="No region data yet." />
                </div>
            </div>
        </section>

        {{-- Breakdown cards --}}
        <div class="grid gap-4 md:grid-cols-2">
            <section class="glass-card" x-data="{ tab: 'links' }">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="section-title">Top links</h2>
                    <div class="flex gap-0.5">
                        <button type="button" @click="tab = 'links'" class="{{ $tabBtn }}" :class="tab === 'links' ? '{{ $tabOn }}' : '{{ $tabOff }}'">Links</button>
                        <button type="button" @click="tab = 'dest'" class="{{ $tabBtn }}" :class="tab === 'dest' ? '{{ $tabOn }}' : '{{ $tabOff }}'">Destinations</button>
                        <button type="button" @click="tab = 'ended'" class="{{ $tabBtn }}" :class="tab === 'ended' ? '{{ $tabOn }}' : '{{ $tabOff }}'">Expired</button>
                    </div>
                </div>
                <div x-show="tab === 'links'">
                    <x-analytics.rank-list :items="collect($topLinks)->filter(fn ($r) => $r->link)->map(fn ($r) => ['label' => '/'.$r->link->slug, 'sub' => $r->link->target_url, 'value' => (int) $r->total, 'href' => route('links.show', $r->link)])" empty="No clicks in this range." />
                </div>
                <div x-show="tab === 'dest'" x-cloak>
                    <x-analytics.rank-list :items="$rows($topDestinations)" empty="No destination data." />
                </div>
                <div x-show="tab === 'ended'" x-cloak>
                    <p class="mb-2 text-xs text-muted">Expired or disabled links that still got clicks in this range.</p>
                    <x-analytics.rank-list :items="collect($endedLinks)->filter(fn ($r) => $r->link)->map(fn ($r) => ['label' => '/'.$r->link->slug, 'sub' => $r->link->target_url, 'value' => (int) $r->total, 'href' => route('links.show', $r->link)])" color="bg-slate-400/60" empty="No clicks on expired links." />
                </div>
            </section>

            <section class="glass-card" x-data="{ tab: 'domains' }">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="section-title">Sources</h2>
                    <div class="flex gap-0.5">
                        <button type="button" @click="tab = 'domains'" class="{{ $tabBtn }}" :class="tab === 'domains' ? '{{ $tabOn }}' : '{{ $tabOff }}'">Domains</button>
                        <button type="button" @click="tab = 'urls'" class="{{ $tabBtn }}" :class="tab === 'urls' ? '{{ $tabOn }}' : '{{ $tabOff }}'">URLs</button>
                    </div>
                </div>
                <div x-show="tab === 'domains'">
                    <x-analytics.rank-list :items="$rows($domainBreakdown)" color="bg-sky-500/70" empty="No referrers yet." />
                </div>
                <div x-show="tab === 'urls'" x-cloak>
                    <x-analytics.rank-list :items="$rows($referers)" color="bg-sky-500/70" empty="No referrers yet." />
                </div>
            </section>

            <section class="glass-card" x-data="{ tab: 'devices' }">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="section-title">Technology</h2>
                    <div class="flex gap-0.5">
                        @foreach (['devices' => 'Devices', 'browsers' => 'Browsers', 'os' => 'OS'] as $key => $label)
                            <button type="button" @click="tab = '{{ $key }}'" class="{{ $tabBtn }}" :class="tab === '{{ $key }}' ? '{{ $tabOn }}' : '{{ $tabOff }}'">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div x-show="tab === 'devices'"><x-analytics.rank-list :items="$rows($devices)" color="bg-emerald-500/70" empty="No device data." /></div>
                <div x-show="tab === 'browsers'" x-cloak><x-analytics.rank-list :items="$rows($browserStats)" color="bg-violet-500/70" empty="No browser data." /></div>
                <div x-show="tab === 'os'" x-cloak><x-analytics.rank-list :items="$rows($osStats)" color="bg-amber-500/70" empty="No OS data." /></div>
            </section>

            <section class="glass-card">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="section-title">Internet providers</h2>
                    @if (($proxyStats['total'] ?? 0) > 0)
                        <span class="chip-amber" title="Clicks from VPN / proxy / hosting networks">{{ number_format($proxyStats['total']) }} via proxy/VPN</span>
                    @endif
                </div>
                <x-analytics.rank-list :items="collect($isps)->take(20)->map(fn ($i) => ['label' => ((object) $i)->isp, 'value' => (int) ((object) $i)->total])" color="bg-rose-500/60" empty="No network data." />
            </section>
        </div>

        {{-- Visitor IPs --}}
        <details class="glass group rounded-2xl">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3">
                <span class="section-title">Recent visitors <span class="font-normal text-muted">({{ $recentIps->count() }})</span></span>
                <svg class="h-4 w-4 text-muted transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
            </summary>
            <div class="overflow-x-auto pb-2">
                <table class="table-glass">
                    <thead><tr><th>Country</th><th class="text-right">When</th></tr></thead>
                    <tbody>
                        @forelse ($recentIps as $hit)
                            <tr>
                                <td class="text-ink"><span class="inline-flex items-center gap-2 [&_img]:rounded-sm">{!! country_flag($hit->country) !!} {{ country_name($hit->country) }}</span></td>
                                <td class="text-right text-xs text-muted">{{ \Carbon\Carbon::parse($hit->created_at)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-sm text-muted">No visitors for this filter.</td></tr>
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
        </style>
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                const dark = () => document.documentElement.classList.contains('dark');
                const labels = @json(array_values($timeline->keys()->toArray()));
                const dataset = @json(array_values($timeline->values()->toArray()));
                const ctx = document.getElementById('analyticsChart');
                const grad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 260);
                grad.addColorStop(0, 'rgba(99,102,241,0.35)');
                grad.addColorStop(1, 'rgba(99,102,241,0)');
                const chart = new Chart(ctx, {
                    type: 'line',
                    data: { labels, datasets: [{ label: 'Clicks', data: dataset, borderColor: '#6366f1', backgroundColor: grad, fill: true, tension: 0.35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, pointBackgroundColor: '#6366f1' }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: { displayColors: false, padding: 10, callbacks: { label: c => c.parsed.y + ' clicks' } },
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
            // Leaflet Choropleth Map (replacing jVectorMap)
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
            window.focusCountry = (countryCode) => {
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
                document.getElementById('map-country-clicks').textContent = clicks ? `${clicks.toLocaleString()} clicks` : 'No clicks yet';
            };
            let map;
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
                fetch('https://raw.githubusercontent.com/johan/world.geo.json/master/countries.geo.json')
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
                                    <p class="text-xs text-slate-600">${clicks.toLocaleString()} clicks</p>
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
                    })
                    .catch(error => {
                        console.error('❌ Map error:', error);
                        document.getElementById('world-map').innerHTML = 
                            '<div class="flex h-full items-center justify-center text-sm text-muted"><p>Map unavailable right now</p></div>';
                    });
            } catch (error) {
                console.error('❌ Map init error:', error);
            }
        </script>
    @endpush
</x-app-layout>
