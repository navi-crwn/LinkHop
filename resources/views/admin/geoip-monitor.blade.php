<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - GeoIP providers</x-slot>
    <x-page-header title="GeoIP providers" subtitle="Lookups fail over by priority, and each IP is cached for 24 hours." />

    @php
        $usageBar = fn (int $used, int $limit) => $limit > 0 ? min(100, $used / $limit * 100) : 0;
        $barColor = fn (float $pct) => $pct > 80 ? 'bg-rose-500' : ($pct > 50 ? 'bg-amber-500' : 'bg-emerald-500');
    @endphp

    <div class="grid items-start gap-3 lg:grid-cols-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:col-span-2">
            @foreach ($providers as $provider)
                <section class="glass-card space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="section-title truncate">{{ $provider['name'] }}</h2>
                        <div class="flex items-center gap-1.5">
                            <span class="chip-gray font-mono">P{{ $provider['priority'] }}</span>
                            <span class="{{ $provider['available'] ? 'chip-green' : 'chip-red' }}">{{ $provider['available'] ? 'Active' : 'Disabled' }}</span>
                        </div>
                    </div>
                    @foreach (['Today' => ['daily_usage', 'daily_limit'], 'This month' => ['monthly_usage', 'monthly_limit']] as $label => [$usedKey, $limitKey])
                        @php $pct = $usageBar((int) $provider[$usedKey], (int) $provider[$limitKey]); @endphp
                        <div>
                            <div class="mb-1 flex justify-between text-xs">
                                <span class="text-muted">{{ $label }}</span>
                                <span class="tabular-nums text-ink">{{ number_format($provider[$usedKey]) }} / {{ number_format($provider[$limitKey]) }}</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-slate-500/15">
                                <div class="{{ $barColor($pct) }} h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>

        <aside class="space-y-3 lg:sticky lg:top-24">
            <form id="testForm" class="glass-card space-y-3">
                @csrf
                <h2 class="section-title">Test a lookup</h2>
                <div class="flex gap-2">
                    <input type="text" id="testIp" value="8.8.8.8" placeholder="IP address" aria-label="IP address" class="input font-mono">
                    <button type="submit" class="btn-primary btn-sm shrink-0">Look up</button>
                </div>
                <pre id="resultData" class="glass-inset hidden max-h-72 overflow-auto rounded-xl p-3 text-xs text-ink"></pre>
            </form>
            <section class="glass-card space-y-2 text-xs text-muted">
                <h2 class="section-title">How it works</h2>
                <p>Providers are tried in priority order, 1 first, and skipped once their daily or monthly quota runs out.</p>
                <p>Quotas reset at midnight and at the start of each month.</p>
            </section>
        </aside>
    </div>

    <script nonce="{{ Vite::cspNonce() }}">
        document.getElementById('testForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const ip = document.getElementById('testIp').value;
            const resultData = document.getElementById('resultData');
            resultData.classList.remove('hidden');
            resultData.textContent = 'Loading...';
            try {
                const response = await fetch('{{ route("admin.geoip.test") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('#testForm [name="_token"]').value
                    },
                    body: JSON.stringify({ ip })
                });
                const data = await response.json();
                resultData.textContent = JSON.stringify(data, null, 2);
            } catch (error) {
                resultData.textContent = 'Error: ' + error.message;
            }
        });
    </script>
</x-app-layout>
