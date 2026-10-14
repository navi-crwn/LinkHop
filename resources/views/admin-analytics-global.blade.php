<x-app-layout>
    <x-slot name="pageTitle">Global Analytics</x-slot>
    @php
        $deviceColors = ['bg-indigo-500', 'bg-emerald-500', 'bg-amber-500'];
        $linkRow = fn ($link) => ['label' => '/'.$link->slug, 'sub' => ($link->user->name ?? 'Guest').' · '.$link->target_url, 'value' => (int) $link->clicks, 'href' => route('links.show', $link)];
    @endphp

    <x-page-header title="Global analytics" subtitle="Platform-wide traffic, growth and sources.">
        <x-slot name="actions">
            <select aria-label="Period" data-nav="?period={value}" class="input !w-auto !py-2">
                <option value="daily" @selected($period === 'daily')>Last 30 days</option>
                <option value="weekly" @selected($period === 'weekly')>Last 12 weeks</option>
                <option value="monthly" @selected($period === 'monthly')>Last 12 months</option>
            </select>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="stat">
            <p class="stat-label">Users</p>
            <p class="stat-value">{{ number_format($totalUsers) }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Links</p>
            <p class="stat-value">{{ number_format($totalLinks) }}</p>
            <p class="text-[11px] text-muted">{{ number_format($activeLinks) }} active</p>
        </div>
        <div class="stat">
            <p class="stat-label">Clicks</p>
            <p class="stat-value">{{ number_format($totalClicks) }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Proxy / VPN</p>
            <p class="stat-value">{{ $proxyPercentage }}%</p>
            <p class="text-[11px] text-muted">{{ number_format($proxyCount) }} clicks</p>
        </div>
    </div>

    {{-- Growth --}}
    @if ($performanceData['clicks']->count() > 0)
        <div class="grid gap-3 lg:grid-cols-3">
            <section class="glass-card lg:col-span-2">
                <h2 class="section-title">Clicks</h2>
                <div class="mt-3 h-64"><canvas id="clicksLine"></canvas></div>
            </section>
            <div class="grid gap-3">
                <section class="glass-card">
                    <h2 class="section-title">Links created</h2>
                    <div class="mt-2 h-24"><canvas id="linksLine"></canvas></div>
                </section>
                <section class="glass-card">
                    <h2 class="section-title">Sign-ups</h2>
                    <div class="mt-2 h-24"><canvas id="usersLine"></canvas></div>
                </section>
            </div>
        </div>
    @else
        <p class="glass-card text-center text-sm text-muted">No performance data for this period yet.</p>
    @endif

    {{-- Ranking --}}
    <div class="grid gap-3 lg:grid-cols-3">
        <section class="glass-card lg:col-span-2" x-data="{ tab: 'live' }">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="section-title">Top links</h2>
                <div class="flex gap-0.5 rounded-lg bg-slate-500/10 p-0.5 text-xs font-medium">
                    <button type="button" @click="tab = 'live'" class="rounded-md px-2.5 py-1" :class="tab === 'live' ? 'bg-white text-slate-900 shadow-sm dark:bg-white/15 dark:text-white' : 'text-muted'">Active</button>
                    <button type="button" @click="tab = 'ended'" class="rounded-md px-2.5 py-1" :class="tab === 'ended' ? 'bg-white text-slate-900 shadow-sm dark:bg-white/15 dark:text-white' : 'text-muted'">Expired &amp; disabled</button>
                </div>
            </div>
            <div x-show="tab === 'live'">
                <x-analytics.rank-list :items="$topLinks->map($linkRow)" :limit="8" empty="No active links with clicks yet." />
            </div>
            <div x-show="tab === 'ended'" x-cloak>
                <p class="mb-2 text-xs text-muted">Links that no longer redirect. They are kept out of the ranking above.</p>
                <x-analytics.rank-list :items="$endedLinks->map($linkRow)" color="bg-slate-400/60" :limit="8" empty="No expired or disabled links with clicks." />
            </div>
        </section>
        <section class="glass-card">
            <h2 class="section-title mb-3">Top countries</h2>
            <x-analytics.rank-list :items="$topCountries->map(fn ($c) => ['label' => $c->country, 'flag' => $c->country, 'value' => (int) $c->total])" :limit="8" color="bg-sky-500/70" empty="No country data yet." />
        </section>
    </div>

    {{-- Map --}}
    <section class="glass-card">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="section-title">Where clicks come from</h2>
            <span class="text-xs text-muted">{{ count($mapData) }} countries</span>
        </div>
        <div class="grid gap-3 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div id="choropleth-map" class="glass-inset w-full overflow-hidden" style="height: 380px;"></div>
                <div class="mt-2 flex items-center justify-end gap-2 text-[10px] text-muted">
                    <span>Low</span>
                    @foreach (['#e2e8f0', '#bfdbfe', '#60a5fa', '#2563eb', '#1d4ed8'] as $swatch)
                        <span class="h-2.5 w-6 rounded-sm" style="background: {{ $swatch }}"></span>
                    @endforeach
                    <span>High</span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="glass-inset px-3 py-2.5">
                    <p class="text-[11px] text-muted">Selected country</p>
                    <p id="global-map-country-name" class="text-sm font-semibold text-ink">Click a country</p>
                    <p id="global-map-country-clicks" class="text-xs text-muted">—</p>
                </div>
                <ul class="space-y-1">
                    @forelse ($topCountries->take(8) as $country)
                        @php $code = strtoupper($country->country ?? ''); @endphp
                        <li>
                            <button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-left text-sm hover:bg-slate-500/10" data-call="focusGlobalCountry" data-args='@json([$code])'>
                                <span class="flex items-center gap-2 text-ink">{!! country_flag($code) !!} {{ $code }}</span>
                                <span class="tabular-nums text-muted">{{ number_format($country->total) }}</span>
                            </button>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-muted">No geo data</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </section>

    {{-- Sources --}}
    <div class="grid gap-3 lg:grid-cols-3">
        <section class="glass overflow-hidden rounded-2xl">
            <h2 class="section-title px-4 pb-2 pt-4">Latest clicks</h2>
            <div class="divide-glass">
                @forelse ($recentClicks as $click)
                    <div class="flex items-center gap-3 px-4 py-2 text-sm">
                        <span class="w-11 shrink-0 font-mono text-[11px] text-muted">{{ $click->created_at?->format('H:i') }}</span>
                        @if ($click->link)
                            <a href="{{ route('links.show', $click->link) }}" class="min-w-0 flex-1 truncate font-mono text-ink hover:underline">/{{ $click->link->slug }}</a>
                        @else
                            <span class="min-w-0 flex-1 truncate text-muted">deleted link</span>
                        @endif
                        <span class="flex shrink-0 items-center gap-1.5 text-xs text-muted">{!! country_flag(strtoupper($click->country ?? '')) !!} {{ $click->country ?? '—' }}</span>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted">No clicks yet.</p>
                @endforelse
            </div>
        </section>
        <section class="glass-card">
            <h2 class="section-title mb-3">Networks (ISP)</h2>
            <x-analytics.rank-list :items="$topIsps->map(fn ($i) => ['label' => $i->isp, 'value' => (int) $i->total])" :limit="8" color="bg-violet-500/70" empty="No ISP data yet." />
        </section>
        <section class="glass-card space-y-5">
            <div>
                <h2 class="section-title">Devices</h2>
                <div class="mt-3 flex items-center gap-4">
                    <div class="h-20 w-20 shrink-0"><canvas id="deviceChart"></canvas></div>
                    <ul class="flex-1 space-y-1 text-xs">
                        @foreach ($devices as $device)
                            <li class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 text-ink"><span class="h-2.5 w-2.5 rounded-full {{ $deviceColors[$loop->index] ?? 'bg-slate-400' }}"></span>{{ $device->device_type }}</span>
                                <span class="tabular-nums text-muted">{{ number_format($device->total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div>
                <h2 class="section-title">Traffic quality</h2>
                <div class="mt-3 flex items-center gap-4">
                    <div class="h-20 w-20 shrink-0"><canvas id="securityChart"></canvas></div>
                    <ul class="flex-1 space-y-1 text-xs">
                        <li class="flex items-center justify-between"><span class="flex items-center gap-1.5 text-ink"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Clean</span><span class="tabular-nums text-muted">{{ number_format($totalClicksCount - $proxyCount) }}</span></li>
                        <li class="flex items-center justify-between"><span class="flex items-center gap-1.5 text-ink"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>Proxy / VPN</span><span class="tabular-nums text-muted">{{ number_format($proxyCount) }}</span></li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
    @push('scripts')
    <style>
        .leaflet-popup-content-wrapper { padding: 5px 7px; border-radius: 10px; }
        .leaflet-popup-content { margin: 0; width: 80px !important; }
        .leaflet-popup-tip { width: 10px; height: 10px; }
    </style>
    <script nonce="{{ Vite::cspNonce() }}">
        document.addEventListener('DOMContentLoaded', function() {
            const mapData = @json($mapData);
            const maxClicks = Math.max(...Object.values(mapData), 1);
            const iso3To2 = {"ATG":"AG","BTN":"BT","ITA":"IT","TUV":"TV","AIA":"AI","AUS":"AU","BLZ":"BZ","VUT":"VU","BLR":"BY","MUS":"MU","LAO":"LA","SEN":"SN","TUR":"TR","BOL":"BO","LKA":"LK","NFK":"NF","CHN":"CN","BES":"BQ","GGY":"GG","SDN":"SD","MYT":"YT","BLM":"BL","VAT":"VA","TCA":"TC","CUW":"CW","BWA":"BW","BEN":"BJ","LTU":"LT","MSR":"MS","VGB":"VG","BDI":"BI","UMI":"UM","IRL":"IE","SLB":"SB","BMU":"BM","FIN":"FI","PER":"PE","BGD":"BD","DNK":"DK","VCT":"VC","DOM":"DO","MDA":"MD","BGR":"BG","CRI":"CR","NAM":"NA","SJM":"SJ","LUX":"LU","RUS":"RU","ARE":"AE","SXM":"SX","BHS":"BS","JPN":"JP","NGA":"NG","GHA":"GH","SLE":"SL","SPM":"PM","ALB":"AL","TKL":"TK","SHN":"SH","TON":"TO","TKM":"TM","DJI":"DJ","CAF":"CF","LBN":"LB","LVA":"LV","CCK":"CC","GMB":"GM","HND":"HN","NIU":"NU","MRT":"MR","UNK":"XK","WLF":"WF","SGS":"GS","PYF":"PF","TGO":"TG","BEL":"BE","ZMB":"ZM","CYM":"KY","PCN":"PN","COK":"CK","MDG":"MG","MNE":"ME","KOR":"KR","ETH":"ET","MNG":"MN","SVK":"SK","CUB":"CU","ATA":"AQ","GTM":"GT","GUF":"GF","NOR":"NO","GRD":"GD","REU":"RE","CHL":"CL","COL":"CO","SAU":"SA","ISR":"IL","DEU":"DE","NZL":"NZ","GRL":"GL","KGZ":"KG","SLV":"SV","FRO":"FO","PLW":"PW","MLT":"MT","SYR":"SY","TLS":"TL","HRV":"HR","PNG":"PG","NLD":"NL","LBR":"LR","SOM":"SO","VEN":"VE","HTI":"HT","DZA":"DZ","MNP":"MP","MAF":"MF","HMD":"HM","ABW":"AW","EGY":"EG","MWI":"MW","GNQ":"GQ","VIR":"VI","ECU":"EC","UZB":"UZ","GAB":"GA","SSD":"SS","IRN":"IR","KAZ":"KZ","NIC":"NI","ISL":"IS","SVN":"SI","GLP":"GP","CMR":"CM","ARG":"AR","AZE":"AZ","UGA":"UG","NER":"NE","CXR":"CX","MMR":"MM","POL":"PL","JOR":"JO","HKG":"HK","COD":"CD","ERI":"ER","KIR":"KI","MHL":"MH","BFA":"BF","ZWE":"ZW","KEN":"KE","COM":"KM","GIB":"GI","BRN":"BN","SWE":"SE","LSO":"LS","IMN":"IM","FSM":"FM","TZA":"TZ","CPV":"CV","AFG":"AF","AND":"AD","GRC":"GR","VNM":"VN","ATF":"TF","IRQ":"IQ","LBY":"LY","PRT":"PT","PAK":"PK","MDV":"MV","MAR":"MA","BIH":"BA","WSM":"WS","PSE":"PS","OMN":"OM","BHR":"BH","USA":"US","PRI":"PR","IOT":"IO","JEY":"JE","MKD":"MK","TUN":"TN","TTO":"TT","EST":"EE","SGP":"SG","PAN":"PA","CHE":"CH","URY":"UY","TJK":"TJ","TWN":"TW","ZAF":"ZA","LIE":"LI","BRA":"BR","ARM":"AM","GEO":"GE","ALA":"AX","QAT":"QA","DMA":"DM","UKR":"UA","GIN":"GN","MAC":"MO","ESH":"EH","CZE":"CZ","AUT":"AT","KNA":"KN","LCA":"LC","YEM":"YE","RWA":"RW","MCO":"MC","STP":"ST","COG":"CG","PRY":"PY","BVT":"BV","MOZ":"MZ","FRA":"FR","SWZ":"SZ","BRB":"BB","ESP":"ES","THA":"TH","GNB":"GW","AGO":"AO","IND":"IN","MTQ":"MQ","NCL":"NC","SYC":"SC","FLK":"FK","GBR":"GB","FJI":"FJ","SMR":"SM","MLI":"ML","CAN":"CA","JAM":"JM","NRU":"NR","IDN":"ID","GUM":"GU","CIV":"CI","KWT":"KW","PHL":"PH","GUY":"GY","HUN":"HU","MEX":"MX","PRK":"KP","ROU":"RO","SUR":"SR","ASM":"AS","NPL":"NP","TCD":"TD","SRB":"RS","KHM":"KH","MYS":"MY","CYP":"CY"};
            const normalizeCountryCode = (code) => {
                if (!code) return '';
                const upper = String(code).toUpperCase();
                if (upper.length === 2) return upper;
                return iso3To2[upper] || upper;
            };
            const countryLayers = {};
            const setGlobalSelected = (name, clicks) => {
                const nameEl = document.getElementById('global-map-country-name');
                const clicksEl = document.getElementById('global-map-country-clicks');
                if (nameEl && clicksEl) {
                    nameEl.textContent = name;
                    clicksEl.textContent = clicks ? `${clicks.toLocaleString()} clicks` : 'No clicks yet';
                }
            };
            window.focusGlobalCountry = (countryCode) => {
                const normalized = normalizeCountryCode(countryCode);
                if (countryLayers[normalized] && countryLayers[normalized].getBounds) {
                    map.fitBounds(countryLayers[normalized].getBounds());
                    countryLayers[normalized].openPopup();
                    const clicks = mapData[normalized] || 0;
                    const name = countryLayers[normalized].feature?.properties?.name || normalized;
                    setGlobalSelected(name, clicks);
                }
            };
            let map;
            try {
                // Initialize map
                map = L.map('choropleth-map', {
                    center: [20, 0],
                    zoom: 2,
                    minZoom: 1,
                    maxZoom: 6,
                    zoomControl: true,
                    scrollWheelZoom: false,
                    worldCopyJump: true,
                    attributionControl: false
                });
                // Add tile layer
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    noWrap: false
                }).addTo(map);
                // Country boundaries GeoJSON
                fetch('https://raw.githubusercontent.com/johan/world.geo.json/master/countries.geo.json')
                    .then(response => {
                        if (!response.ok) throw new Error('Failed to fetch GeoJSON: ' + response.status);
                        return response.json();
                    })
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
                            layer.setStyle({
                                weight: 2,
                                color: '#475569',
                                fillOpacity: 0.95
                            });
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
                                    map.fitBounds(e.target.getBounds());
                                    setGlobalSelected(countryName, clicks);
                                }
                            });
                        }
                        const geojson = L.geoJSON(geoData, {
                            style: style,
                            onEachFeature: onEachFeature
                        }).addTo(map);
                    })
                    .catch(error => {
                        console.error('❌ Map loading error:', error);
                        document.getElementById('choropleth-map').innerHTML = 
                            '<div class="flex items-center justify-center h-full"><div class="text-center"><p class="text-sm text-red-600 font-semibold">Map Failed to Load</p><p class="text-xs text-slate-500 mt-1">Please try again.</p><button type="button" data-reload class="mt-2 px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">Retry</button></div></div>';
                    });
            } catch (error) {
                console.error('❌ Map initialization error:', error);
                document.getElementById('choropleth-map').innerHTML = 
                    '<div class="flex items-center justify-center h-full text-red-600"><p class="text-sm">Failed to initialize map: ' + error.message + '</p></div>';
            }
            const labelsLine = {!! json_encode($performanceData['clicks']->keys()) !!};
            const clicksValues = {!! json_encode(array_values($performanceData['clicks']->toArray())) !!};
            const linksValues = {!! json_encode(array_values($performanceData['links']->toArray())) !!};
            const usersValues = {!! json_encode(array_values($performanceData['users']->toArray())) !!};
            const buildLine = (id, data, color) => {
                const el = document.getElementById(id);
                if (!el) return;
                new Chart(el, {
                    type: 'line',
                    data: {
                        labels: labelsLine,
                        datasets: [{
                            data,
                            borderColor: color,
                            backgroundColor: color + '33',
                            borderWidth: 2,
                            tension: 0.35,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { autoSkip: true, maxTicksLimit: 6 } },
                            y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.15)' } }
                        }
                    }
                });
            };
            buildLine('clicksLine', clicksValues, '#3b82f6');
            buildLine('linksLine', linksValues, '#10b981');
            buildLine('usersLine', usersValues, '#8b5cf6');
            // Device Chart (SMALLER)
            const deviceData = @json($devices);
            const deviceLabels = deviceData.map(d => d.device_type);
            const deviceCounts = deviceData.map(d => d.total);
            const deviceColors = ['#6366f1', '#10b981', '#f59e0b'];
            const deviceChart = new Chart(document.getElementById('deviceChart'), {
                type: 'doughnut',
                data: {
                    labels: deviceLabels,
                    datasets: [{
                        data: deviceCounts,
                        backgroundColor: deviceColors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
            // Security Chart (SMALLER)
            const cleanTraffic = {{ $totalClicksCount - $proxyCount }};
            const proxyTraffic = {{ $proxyCount }};
            const securityChart = new Chart(document.getElementById('securityChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Clean Traffic', 'Proxy/VPN'],
                    datasets: [{
                        data: [cleanTraffic, proxyTraffic],
                        backgroundColor: ['#10b981', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
