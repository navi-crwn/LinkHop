<x-app-layout>
    <x-slot name="pageTitle">LinkHop - Dashboard</x-slot>
    @php
        $pct = fn ($q) => $q['limit'] > 0 ? min(100, round($q['used'] / $q['limit'] * 100)) : 0;
        $flag = function (?string $code) {
            $code = strtolower((string) $code);
            return $code && file_exists(public_path("images/flags/{$code}.svg")) ? "/images/flags/{$code}.svg" : null;
        };
    @endphp

    @php $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ')->toString(); @endphp
    <div x-data x-init="const h = new Date().getHours(); $el.querySelector('h1').textContent = h < 11 ? @js(__('Good morning, :name', ['name' => $firstName])) : h < 18 ? @js(__('Good afternoon, :name', ['name' => $firstName])) : @js(__('Good evening, :name', ['name' => $firstName]))">
        <x-page-header :title="__('Hi, :name', ['name' => $firstName])" :subtitle="__(':count of :max links created today', ['count' => $quota['daily']['used'], 'max' => $quota['daily']['limit']])" />
    </div>

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

    {{-- This week against last, the totals, and what to do next --}}
    <div class="grid gap-3 md:grid-cols-3">
        <section class="glass-card flex flex-col justify-between gap-4 md:col-span-2" aria-labelledby="week-title">
            <div class="flex flex-1 flex-wrap items-stretch justify-between gap-x-6 gap-y-3">
                <div>
                    <p id="week-title" class="stat-label">{{ __('Clicks this week') }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-ink">{{ number_format($week['total']) }}</p>
                    @if ($week['delta'] !== null)
                        <p class="mt-0.5 text-xs {{ $week['delta'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">{{ $week['delta'] >= 0 ? '+' : '' }}{{ $week['delta'] }}% {{ __('against last week') }}</p>
                    @else
                        <p class="mt-0.5 text-xs text-muted">{{ __('No clicks last week to compare with') }}</p>
                    @endif
                </div>
                <div class="flex min-h-24 min-w-0 flex-1 items-stretch gap-1.5 sm:max-w-md" role="img" aria-label="{{ __('Clicks per day, last seven days') }}">
                    @foreach ($week['week'] as $i => $n)
                        @php $d = now()->subDays(6 - $i); @endphp
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-1">
                            <div class="flex w-full flex-1 items-end">
                                <div class="w-full rounded-t-md bg-indigo-500/80" style="height: {{ max(4, round($n / $week['max'] * 100)) }}%" title="{{ $d->format('D d M') }}: {{ $n }}"></div>
                            </div>
                            <span class="text-[10px] text-muted">{{ $d->isoFormat('dd') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <dl class="grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
                <div class="glass-inset rounded-xl py-2"><dt class="text-[11px] text-muted">{{ __('Total clicks') }}</dt><dd class="text-sm font-semibold tabular-nums text-ink">{{ number_format($stats['total_clicks'] ?? 0) }}</dd></div>
                <div class="glass-inset rounded-xl py-2"><dt class="text-[11px] text-muted">{{ __('Links') }}</dt><dd class="text-sm font-semibold tabular-nums text-ink">{{ number_format($stats['total_links'] ?? 0) }}</dd></div>
                <div class="glass-inset rounded-xl py-2 max-sm:hidden"><dt class="text-[11px] text-muted">{{ __('Active') }}</dt><dd class="text-sm font-semibold tabular-nums text-ink">{{ number_format($stats['active_links'] ?? 0) }}</dd></div>
                <div class="glass-inset rounded-xl py-2"><dt class="text-[11px] text-muted">{{ __('Top location') }}</dt>
                    <dd class="flex items-center justify-center gap-1.5 text-sm font-semibold text-ink">
                        @if ($topCountry && $flag($topCountry->country))<img src="{{ $flag($topCountry->country) }}" alt="" class="h-3.5 w-5 rounded-xs object-cover">@endif
                        {{ $topCountry->country ?? '—' }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="glass-card" data-checklist aria-labelledby="start-title">
            <div class="flex items-center justify-between gap-2">
                <h2 id="start-title" class="section-title">{{ $checklist['done'] >= count($checklist['items']) ? __('You have tried everything') : __('Get the most out of LinkHop') }}</h2>
                <span class="text-xs tabular-nums text-muted">{{ __(':done of :total', ['done' => $checklist['done'], 'total' => count($checklist['items'])]) }}</span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" aria-hidden="true"><div class="h-full rounded-full bg-indigo-500" style="width: {{ round($checklist['done'] / count($checklist['items']) * 100) }}%"></div></div>
            <ul class="mt-3 space-y-1.5 text-sm">
                @foreach ($checklist['items'] as $item)
                    <li>
                        <a href="{{ route($item['route'], $item['params']) }}" class="flex items-center gap-2.5 rounded-lg px-1 py-1 {{ $item['done'] ? 'text-muted' : 'text-ink hover:text-indigo-600 dark:hover:text-indigo-300' }}">
                            <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border {{ $item['done'] ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 dark:border-slate-600' }}" aria-hidden="true">
                                @if ($item['done'])<svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                            </span>
                            <span class="{{ $item['done'] ? 'line-through' : '' }}">{{ __($item['label']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="grid gap-3 lg:grid-cols-3">
        {{-- Recent links, each with its own seven-day line --}}
        <section class="glass min-w-0 overflow-hidden rounded-2xl lg:col-span-2">
            <div class="flex items-center justify-between px-4 pb-2 pt-4">
                <h2 class="section-title">{{ __('Recent links') }}</h2>
                <a href="{{ route('links.index') }}" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-300">{{ __('View all') }}</a>
            </div>
            <div class="divide-glass">
                @forelse ($latestLinks as $link)
                    <div class="list-row">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('links.show', $link) }}" class="truncate text-sm font-semibold text-ink hover:text-indigo-600 dark:hover:text-indigo-300">/l/{{ $link->slug }}</a>
                                @if ($link->isExpired())
                                    <span class="chip-red">{{ __('Expired') }}</span>
                                @elseif ($link->status !== 'active')
                                    <span class="chip-gray">{{ ucfirst($link->status) }}</span>
                                @endif
                                @if ($link->isNotStarted())
                                    <span class="chip-gray whitespace-nowrap" title="{{ $link->starts_at }}">{{ __('Starts in :when', ['when' => $link->starts_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true)]) }}</span>
                                @endif
                                @if ($link->isLimitReached())
                                    <span class="chip-red whitespace-nowrap">{{ __('Limit reached') }}</span>
                                @elseif ($link->max_clicks)
                                    <span class="chip-gray whitespace-nowrap">{{ number_format($link->clicks) }}/{{ number_format($link->max_clicks) }}</span>
                                @endif
                                @if ($link->password_hash)<span class="chip-gray" title="{{ __('Password') }}">{{ __('Password') }}</span>@endif
                            </div>
                            <p class="truncate text-xs text-muted">{{ $link->target_url }}</p>
                        </div>
                        <x-spark :values="$sparks[$link->id] ?? []" class="hidden sm:block" />
                        <div class="w-14 text-right">
                            <p class="text-sm font-semibold tabular-nums text-ink">{{ number_format($link->clicks) }}</p>
                            <p class="text-[11px] text-muted">{{ __('clicks') }}</p>
                        </div>
                        <div class="flex items-center">
                            <button type="button" class="icon-btn" title="{{ __('Copy link') }}" aria-label="{{ __('Copy link') }}" data-copied="{{ __('Copied') }}" data-failed="{{ __('Copy failed') }}" x-data @click="hopCopy(@js($link->short_url), $el)">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/></svg>
                            </button>
                            <a href="{{ route('links.edit', $link) }}" class="icon-btn" title="{{ __('Edit') }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m15 5 4 4L8 20H4v-4L15 5Z"/></svg>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-10 text-center">
                        <p class="text-sm text-muted">{{ __('No links yet.') }}</p>
                        <button type="button" data-call="openLinkPanel" class="btn-primary btn-sm mt-3">{{ __('Create your first link') }}</button>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="grid min-w-0 content-start gap-3">
            <section class="glass-card min-w-0">
                <h2 class="section-title">{{ __('Top performer') }}</h2>
                @if ($highlightTop && $highlightTop->clicks > 0)
                    <a href="{{ route('links.show', $highlightTop) }}" class="mt-2 block truncate text-sm font-semibold text-indigo-600 dark:text-indigo-300">/l/{{ $highlightTop->slug }}</a>
                    <p class="truncate text-xs text-muted">{{ $highlightTop->target_url }}</p>
                    <div class="mt-2 flex items-end justify-between gap-3">
                        <p class="min-w-0 text-2xl font-semibold tabular-nums text-ink">{{ number_format($highlightTop->clicks) }} <span class="text-xs font-normal text-muted">{{ __('clicks') }}</span></p>
                        <x-spark :values="$sparks[$highlightTop->id] ?? []" class="h-9 w-28 shrink-0" />
                    </div>
                @else
                    <p class="mt-2 text-sm text-muted">{{ __('Share a link to see it here.') }}</p>
                @endif
            </section>

            <section class="glass-card">
                <div class="flex items-center justify-between">
                    <h2 class="section-title">{{ __('Usage') }}</h2>
                    <span class="text-[11px] text-muted">{{ __('resets daily') }}</span>
                </div>
                <dl class="mt-3 space-y-3 text-xs">
                    @foreach (array_filter([[__('Created today'), $quota['daily'], 'bg-indigo-500'], [__('Active links'), $quota['active'], 'bg-emerald-500'], isset($quota['bio']) ? [__('Bio pages'), $quota['bio'], 'bg-violet-500'] : null]) as [$label, $q, $color])
                        <div class="flex flex-wrap justify-between">
                            <dt class="mb-1 text-muted">{{ $label }}</dt>
                            <dd class="mb-1 font-semibold tabular-nums text-ink">{{ $q['used'] }} / {{ $q['limit'] }}</dd>
                            <dd class="w-full"><div class="progress" aria-hidden="true"><span class="{{ $color }}" style="width: {{ max(2, $pct($q)) }}%"></span></div></dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>
    </div>

    <x-link-creation-modal :folders="$folders" :tags="$tags" :isAdmin="auth()->user()->isSuperAdmin()" />
</x-app-layout>
