<x-app-layout>
    <x-slot name="pageTitle">LinkHop - {{ $bioPage->title }}</x-slot>
    <x-page-header :title="$bioPage->title" subtitle="/b/{{ $bioPage->slug }} · created {{ $bioPage->created_at->diffForHumans() }}">
        <x-slot name="actions">
            <a href="{{ route('admin.bio.index') }}" class="btn-ghost btn-sm">All bio pages</a>
            <a href="{{ route('bio.public.show', $bioPage->slug) }}" target="_blank" rel="noopener" class="btn-soft btn-sm">Open page</a>
            <form action="{{ route('admin.bio.toggle', $bioPage) }}" method="POST">
                @csrf
                <button type="submit" class="btn-soft btn-sm">{{ $bioPage->is_published ? 'Unpublish' : 'Publish' }}</button>
            </form>
            <form action="{{ route('admin.bio.destroy', $bioPage) }}" method="POST" data-confirm="Delete this bio page permanently?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger btn-sm">Delete</button>
            </form>
        </x-slot>
    </x-page-header>

    <x-flash />

    @php
        $activeLinks = $bioPage->links->where('is_active', true)->count();
    @endphp

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="stat"><p class="stat-label">Status</p><p class="mt-1"><span class="{{ $bioPage->is_published ? 'chip-green' : 'chip-gray' }}">{{ $bioPage->is_published ? 'Published' : 'Draft' }}</span></p></div>
        <div class="stat"><p class="stat-label">Views</p><p class="stat-value">{{ number_format($bioPage->view_count) }}</p></div>
        <div class="stat"><p class="stat-label">Link clicks</p><p class="stat-value">{{ number_format($bioPage->links->sum('click_count')) }}</p></div>
        <div class="stat"><p class="stat-label">Active links</p><p class="stat-value">{{ $activeLinks }}<span class="text-sm font-normal text-muted"> / {{ $bioPage->links->count() }}</span></p></div>
    </div>

    <div class="grid items-start gap-3 lg:grid-cols-3">
        <section class="glass overflow-hidden rounded-2xl lg:col-span-2">
            <div class="flex items-center justify-between px-4 pb-2 pt-4">
                <h2 class="section-title">Links</h2>
                <span class="text-xs text-muted">{{ $bioPage->links->count() }}</span>
            </div>
            <ul class="divide-glass">
                @forelse ($bioPage->links as $link)
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        @if ($link->thumbnail_url)
                            <img src="{{ Storage::disk('public')->url($link->thumbnail_url) }}" class="h-8 w-8 shrink-0 rounded-lg object-cover" alt="">
                        @else
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-500/10 text-muted">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            </span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink">{{ $link->displayTitle() }}</p>
                            @if ($summaryUrl = $link->displaySummaryUrl())
                                <a href="{{ $summaryUrl }}" target="_blank" rel="noopener nofollow" class="block truncate text-xs text-muted hover:underline">{{ $link->displaySummary() }}</a>
                            @elseif ($summary = $link->displaySummary())
                                <p class="truncate text-xs text-muted">{{ $summary }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs tabular-nums text-muted">{{ number_format($link->click_count) }} clicks</span>
                        <span class="{{ $link->is_active ? 'chip-green' : 'chip-red' }} shrink-0">{{ $link->is_active ? 'Active' : 'Off' }}</span>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-sm text-muted">No links added yet.</li>
                @endforelse
            </ul>
        </section>

        <div class="space-y-3">
            <section class="glass-card space-y-3">
                <h2 class="section-title">Owner</h2>
                @if ($bioPage->user)
                    <a href="{{ route('admin.users.inspect', $bioPage->user) }}" class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-sm font-semibold text-indigo-600 dark:text-indigo-300">{{ strtoupper(mb_substr($bioPage->user->name, 0, 1)) }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $bioPage->user->name }}</span>
                            <span class="block truncate text-xs text-muted">{{ $bioPage->user->email }}</span>
                        </span>
                    </a>
                    <dl class="space-y-1 text-xs">
                        <div class="flex justify-between"><dt class="text-muted">User ID</dt><dd class="text-ink">#{{ $bioPage->user->id }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Joined</dt><dd class="text-ink">{{ $bioPage->user->created_at?->format('d M Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Page updated</dt><dd class="text-ink">{{ $bioPage->updated_at->diffForHumans() }}</dd></div>
                    </dl>
                @else
                    <p class="text-sm text-muted">Owner account no longer exists.</p>
                @endif
                @if ($bioPage->description)
                    <p class="border-t border-slate-500/10 pt-3 text-xs text-muted">{{ $bioPage->description }}</p>
                @endif
            </section>

            <section class="glass overflow-hidden rounded-2xl">
                <h2 class="section-title px-4 pb-2 pt-4">Recent clicks</h2>
                <ul class="divide-glass">
                    @forelse ($recentClicks->take(10) as $click)
                        <li class="flex items-center justify-between gap-2 px-4 py-2 text-xs">
                            <span class="truncate text-ink">{{ $click->bioLink?->displayTitle() ?? 'Removed link' }}</span>
                            <span class="flex shrink-0 items-center gap-1.5 text-muted">@if ($click->country){!! country_flag($click->country) !!}@endif{{ $click->created_at->diffForHumans(null, true) }}</span>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-sm text-muted">No activity yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
