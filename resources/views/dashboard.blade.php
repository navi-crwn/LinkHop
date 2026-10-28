<x-app-layout>
    <x-slot name="pageTitle">LinkHop - Dashboard</x-slot>
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

    @if (\App\Support\ShortLinkMove::active())
        {{-- Short links moved to hel.ink/l/{slug}; the old form redirects until the date below. --}}
        <div class="glass flex flex-wrap items-center gap-3 rounded-2xl px-4 py-3 text-sm">
            <span class="chip-blue">Notice</span>
            <span class="min-w-0 flex-1 text-muted">Short links now live at <strong class="text-ink">{{ public_host() }}/l/&hellip;</strong> The old form ({{ public_host() }}/&hellip;) keeps redirecting until <strong class="text-ink">{{ \App\Support\ShortLinkMove::untilLabel() }}</strong>, then stops.</span>
            <a href="{{ route('announcements.short-links') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-300">What to do &rarr;</a>
        </div>
    @endif

    @if (auth()->user()->isAdmin())
        {{-- The admin pages moved to the HOP control center (Imperium). --}}
        <div class="glass flex flex-wrap items-center gap-3 rounded-2xl px-4 py-3 text-sm">
            <span class="chip-blue">Admin</span>
            <span class="min-w-0 flex-1 text-muted">Moderation and platform settings live in {{ config('hop.control_name') }}, the HOP control center.</span>
            @if (config('hop.control_url'))
                <a href="{{ config('hop.control_url') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-300">Open {{ config('hop.control_name') }} &rarr;</a>
            @else
                <a href="{{ route('admin.control') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-300">Where did the admin panel go?</a>
            @endif
        </div>
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
                    <img src="{{ $flag($topCountry->country) }}" alt="" class="h-4 w-6 rounded-xs object-cover">
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
                                <a href="{{ route('links.show', $link) }}" class="truncate text-sm font-semibold text-ink hover:text-indigo-600 dark:hover:text-indigo-300">/l/{{ $link->slug }}</a>
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
                            <button type="button" class="icon-btn" title="Copy link" x-data @click="navigator.clipboard.writeText(@js($link->short_url)); $el.classList.add('text-emerald-500!'); setTimeout(() => $el.classList.remove('text-emerald-500!'), 1200)">
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
                        <button type="button" data-call="openLinkPanel" class="btn-primary btn-sm mt-3">Create your first link</button>
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
                    @foreach (array_filter([['Created today', $quota['daily'], 'bg-indigo-500'], ['Active links', $quota['active'], 'bg-emerald-500'], isset($quota['bio']) ? ['Bio pages', $quota['bio'], 'bg-violet-500'] : null]) as [$label, $q, $color])
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
                    <a href="{{ route('links.show', $highlightTop) }}" class="mt-2 block truncate text-sm font-semibold text-indigo-600 dark:text-indigo-300">/l/{{ $highlightTop->slug }}</a>
                    <p class="truncate text-xs text-muted">{{ $highlightTop->target_url }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-ink">{{ number_format($highlightTop->clicks) }} <span class="text-xs font-normal text-muted">clicks</span></p>
                @else
                    <p class="mt-2 text-sm text-muted">Share a link to see it here.</p>
                @endif
            </section>
        </div>
    </div>


    <x-link-creation-modal :folders="$folders" :tags="$tags" :isAdmin="auth()->user()->isSuperAdmin()" />
</x-app-layout>
