<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Dashboard</x-slot>
    @php
        $pct = fn ($q) => $q['limit'] > 0 ? min(100, round($q['used'] / $q['limit'] * 100)) : 0;
        $flag = function (?string $code) {
            $code = strtolower((string) $code);
            return $code && file_exists(public_path("images/flags/{$code}.svg")) ? "/images/flags/{$code}.svg" : null;
        };
    @endphp

    <x-page-header title="Hi, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}" subtitle="Here's how your links are doing." />

    @if (session('status'))
        <div class="glass rounded-2xl px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">{{ session('status') }}</div>
    @endif

    @if (session('shortlink'))
        @php $newLink = session('shortlink'); @endphp
        <div class="glass-card flex flex-col gap-3 sm:flex-row sm:items-center" x-data="{ copied: false }">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-300">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="eyebrow">Link created</p>
                <a href="{{ $newLink->short_url }}" target="_blank" class="block truncate font-mono text-base font-semibold text-indigo-600 dark:text-indigo-300">{{ $newLink->short_url }}</a>
                <p class="truncate text-xs text-muted">→ {{ $newLink->target_url }}</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn-primary btn-sm" @click="navigator.clipboard.writeText(@js($newLink->short_url)); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                </button>
                <a href="{{ route('links.show', $newLink) }}" class="btn-soft btn-sm">Stats</a>
            </div>
        </div>
    @endif

    {{-- Key metrics --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="stat">
            <p class="stat-label">Total clicks</p>
            <p class="stat-value">{{ number_format($stats['total_clicks'] ?? 0) }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Links</p>
            <p class="stat-value">{{ number_format($stats['total_links'] ?? 0) }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Active</p>
            <p class="stat-value">{{ number_format($stats['active_links'] ?? 0) }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Top location</p>
            <div class="mt-0.5 flex items-center gap-2">
                @if ($topCountry && $flag($topCountry->country))
                    <img src="{{ $flag($topCountry->country) }}" alt="" class="h-4 w-6 rounded-sm object-cover">
                @endif
                <p class="stat-value">{{ $topCountry->country ?? '—' }}</p>
                @if ($topCountry)
                    <span class="text-xs text-muted">{{ number_format($topCountry->total) }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Recent links --}}
        <section class="glass overflow-hidden rounded-2xl lg:col-span-2">
            <div class="flex items-center justify-between px-4 pb-2 pt-4">
                <h2 class="section-title">Recent links</h2>
                <a href="{{ route('links.index') }}" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-300">View all</a>
            </div>
            <div class="divide-glass">
                @forelse ($latestLinks as $link)
                    <div class="list-row">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('links.show', $link) }}" class="truncate text-sm font-semibold text-ink hover:text-indigo-600 dark:hover:text-indigo-300">/{{ $link->slug }}</a>
                                @if ($link->isExpired())
                                    <span class="chip-red">Expired</span>
                                @elseif ($link->status !== 'active')
                                    <span class="chip-gray">{{ ucfirst($link->status) }}</span>
                                @endif
                            </div>
                            <p class="truncate text-xs text-muted">{{ $link->target_url }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold tabular-nums text-ink">{{ number_format($link->clicks) }}</p>
                            <p class="text-[11px] text-muted">clicks</p>
                        </div>
                        <div class="flex items-center">
                            <button type="button" class="icon-btn" title="Copy link" x-data @click="navigator.clipboard.writeText(@js($link->short_url)); $el.classList.add('!text-emerald-500'); setTimeout(() => $el.classList.remove('!text-emerald-500'), 1200)">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/></svg>
                            </button>
                            <a href="{{ route('links.edit', $link) }}" class="icon-btn" title="Edit">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m15 5 4 4L8 20H4v-4L15 5Z"/></svg>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-10 text-center">
                        <p class="text-sm text-muted">No links yet.</p>
                        <button type="button" onclick="window.openLinkPanel && window.openLinkPanel()" class="btn-primary btn-sm mt-3">Create your first link</button>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="space-y-4">
            {{-- Quotas --}}
            <section class="glass-card">
                <div class="flex items-center justify-between">
                    <h2 class="section-title">Usage</h2>
                    <span class="text-[11px] text-muted">resets daily</span>
                </div>
                <dl class="mt-3 space-y-3 text-xs">
                    @foreach ([['Created today', $quota['daily'], 'bg-indigo-500'], ['Active links', $quota['active'], 'bg-emerald-500'], ['Bio pages', $quota['bio'], 'bg-violet-500']] as [$label, $q, $color])
                        {{-- dt/dd must be direct children of the group div (dl > div > dt + dd) --}}
                        <div class="flex flex-wrap justify-between">
                            <dt class="mb-1 text-muted">{{ $label }}</dt>
                            <dd class="mb-1 font-semibold tabular-nums text-ink">{{ $q['used'] }} / {{ $q['limit'] }}</dd>
                            <dd class="w-full"><div class="progress" aria-hidden="true"><span class="{{ $color }}" style="width: {{ max(2, $pct($q)) }}%"></span></div></dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            {{-- Highlight --}}
            <section class="glass-card">
                <h2 class="section-title">Top performer</h2>
                @if ($highlightTop && $highlightTop->clicks > 0)
                    <a href="{{ route('links.show', $highlightTop) }}" class="mt-2 block truncate text-sm font-semibold text-indigo-600 dark:text-indigo-300">/{{ $highlightTop->slug }}</a>
                    <p class="truncate text-xs text-muted">{{ $highlightTop->target_url }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-ink">{{ number_format($highlightTop->clicks) }} <span class="text-xs font-normal text-muted">clicks</span></p>
                @else
                    <p class="mt-2 text-sm text-muted">Share a link to see it here.</p>
                @endif
            </section>
        </div>
    </div>

    @isset($adminStats)
        {{-- Platform overview (superadmin) --}}
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="section-title">Platform</h2>
                <a href="{{ route('admin.analytics.global') }}" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-300">Platform analytics</a>
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="stat"><p class="stat-label">Users</p><p class="stat-value">{{ number_format($adminStats['total_users']) }}</p></div>
                <div class="stat"><p class="stat-label">Links</p><p class="stat-value">{{ number_format($adminStats['total_platform_links']) }}</p></div>
                <div class="stat"><p class="stat-label">Clicks today</p><p class="stat-value">{{ number_format($adminStats['today_platform_clicks']) }}</p></div>
                <div class="stat">
                    <p class="stat-label">Queue</p>
                    <p class="stat-value text-base sm:text-lg">
                        @if ($adminStats['queue_heartbeat'])
                            <span class="chip-green">Running</span>
                        @else
                            <span class="chip-red">Stale</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="glass overflow-hidden rounded-2xl">
                    <h3 class="px-4 pb-2 pt-4 text-xs font-semibold text-muted">Newest users</h3>
                    <div class="divide-glass">
                        @foreach ($recentUsers as $u)
                            <a href="{{ route('admin.users.inspect', $u) }}" class="list-row">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-ink">{{ $u->email }}</p>
                                    <p class="text-[11px] text-muted">{{ $u->created_at->diffForHumans() }}</p>
                                </div>
                                @if ($u->status === 'suspended')
                                    <span class="chip-red">Suspended</span>
                                @elseif (! $u->email_verified_at)
                                    <span class="chip-amber">Unverified</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="glass overflow-hidden rounded-2xl">
                    <h3 class="px-4 pb-2 pt-4 text-xs font-semibold text-muted">Newest links</h3>
                    <div class="divide-glass">
                        @foreach ($recentPlatformLinks as $l)
                            <div class="list-row">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-ink">/{{ $l->slug }}</p>
                                    <p class="truncate text-[11px] text-muted">{{ $l->target_url }}</p>
                                </div>
                                <span class="text-[11px] text-muted">{{ $l->user->email ?? 'guest' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endisset

    <x-link-creation-modal :folders="$folders" :tags="$tags" :isAdmin="auth()->user()->isSuperAdmin()" />
</x-app-layout>
