<x-app-layout>
    <x-slot name="pageTitle">LinkHop - Proxy detection</x-slot>
    <x-page-header title="Proxy detection" subtitle="Detectors vote on each IP, weighted by confidence.">
        <x-slot name="actions">
            <form action="{{ route('admin.proxy.reset-stats') }}" method="POST" data-confirm="Reset all detector statistics?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-soft btn-sm">Reset stats</button>
            </form>
        </x-slot>
    </x-page-header>

    <x-flash />

    <div class="grid items-start gap-3 lg:grid-cols-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:col-span-2">
            @foreach ($detectorStats as $name => $data)
                <section class="glass-card space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="section-title truncate">{{ $name }}</h2>
                        <div class="flex items-center gap-1.5">
                            <span class="chip-gray font-mono">P{{ $data['priority'] }}</span>
                            <span class="{{ $data['enabled'] ? 'chip-green' : 'chip-gray' }}">{{ $data['enabled'] ? 'Active' : 'Disabled' }}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="glass-inset rounded-xl px-3 py-2">
                            <p class="stat-label">Checks</p>
                            <p class="text-base font-semibold tabular-nums text-ink">{{ number_format($data['stats']['total_checks']) }}</p>
                        </div>
                        <div class="glass-inset rounded-xl px-3 py-2">
                            <p class="stat-label">Detected</p>
                            <p class="text-base font-semibold tabular-nums text-rose-600 dark:text-rose-400">{{ number_format($data['stats']['positive_detections']) }} <span class="text-xs font-normal text-muted">{{ $data['detection_rate'] }}%</span></p>
                        </div>
                    </div>
                    <dl class="space-y-1 text-xs">
                        <div class="flex justify-between"><dt class="text-muted">Quota</dt><dd class="text-ink">{{ $data['quota'] }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Errors</dt><dd class="tabular-nums {{ $data['stats']['errors'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-ink' }}">{{ number_format($data['stats']['errors']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Last check</dt><dd class="text-ink">{{ $data['stats']['last_check'] ? \Carbon\Carbon::parse($data['stats']['last_check'])->diffForHumans() : '—' }}</dd></div>
                    </dl>
                </section>
            @endforeach
        </div>

        <aside class="space-y-3 lg:sticky lg:top-24">
            <section class="glass-card space-y-3" x-data="proxyTester()">
                <h2 class="section-title">Test an IP</h2>
                <div class="flex gap-2">
                    <input x-model="testIp" type="text" placeholder="8.8.8.8" aria-label="IP address" class="input font-mono" @keyup.enter="testProxy">
                    <button type="button" @click="testProxy" :disabled="loading" class="btn-primary btn-sm shrink-0">
                        <span x-text="loading ? 'Testing…' : 'Test'"></span>
                    </button>
                </div>
                <p x-show="error" x-cloak x-text="error" class="text-xs text-rose-500"></p>
                <div x-show="result" x-cloak class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span :class="result?.is_proxy ? 'chip-red' : 'chip-green'" x-text="result?.is_proxy ? 'Proxy / VPN' : 'Clean IP'"></span>
                        <span class="font-mono text-xs text-muted" x-text="testIp"></span>
                    </div>
                    <dl class="grid grid-cols-3 gap-2 text-xs">
                        <div><dt class="text-muted">Type</dt><dd class="font-medium text-ink" x-text="result?.type || 'Unknown'"></dd></div>
                        <div><dt class="text-muted">Confidence</dt><dd class="font-medium text-ink" x-text="(result?.confidence ?? 0) + '%'"></dd></div>
                        <div><dt class="text-muted">Consensus</dt><dd class="font-medium text-ink" x-text="result?.consensus_strength || 'N/A'"></dd></div>
                    </dl>
                    <ul x-show="result?.detectors" class="divide-glass glass-inset rounded-xl">
                        <template x-for="(detection, name) in result?.detectors" :key="name">
                            <li class="flex items-center justify-between px-3 py-1.5 text-xs">
                                <span class="text-ink" x-text="name"></span>
                                <span :class="detection.is_proxy ? 'text-rose-500' : 'text-emerald-600 dark:text-emerald-400'" x-text="(detection.is_proxy ? 'Proxy' : 'Clean') + ' · ' + detection.confidence + '%'"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </section>
            <section class="glass-card space-y-2 text-xs text-muted">
                <h2 class="section-title">Confidence levels</h2>
                <p>High above 70%, medium 40–70%, low below 40%. ISP pattern matching runs locally as the unlimited fallback.</p>
            </section>
        </aside>
    </div>

    @push('scripts')
    <script nonce="{{ Vite::cspNonce() }}">
        function proxyTester() {
            return {
                testIp: '',
                loading: false,
                result: null,
                error: null,
                async testProxy() {
                    if (!this.testIp) {
                        this.error = 'Please enter an IP address';
                        return;
                    }
                    this.loading = true;
                    this.error = null;
                    this.result = null;
                    try {
                        const response = await fetch('{{ route("admin.proxy.test") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ ip: this.testIp })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.result = data.result;
                        } else {
                            this.error = data.message || 'Test failed';
                        }
                    } catch (err) {
                        this.error = 'Network error: ' + err.message;
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
