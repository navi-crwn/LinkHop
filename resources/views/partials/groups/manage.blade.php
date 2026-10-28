{{--
    Shared folder / tag detail page.
    Expects: $kind ('folder'|'tag'), $group, $links (paginator), $search.
--}}
@php
    $plural = $kind.'s';
    $label = ucfirst($kind);
    $isDefault = $kind === 'folder' && $group->is_default;
@endphp

<x-page-header :title="$group->name" subtitle="{{ $label }} · {{ number_format($links->total()) }} {{ Str::plural('link', $links->total()) }} · created {{ optional($group->created_at)->diffForHumans() }}">
    <x-slot name="actions">
        <a href="{{ route($plural.'.index') }}" class="btn-ghost btn-sm">All {{ $plural }}</a>
        <a href="{{ route('links.index', [$kind => $group->id]) }}" class="btn-soft btn-sm">Open in My links</a>
    </x-slot>
</x-page-header>

<x-flash />

<div class="grid items-start gap-3 lg:grid-cols-[16rem_minmax(0,1fr)]">
    <aside class="space-y-3 lg:sticky lg:top-24">
        <section class="glass-card space-y-3">
            <h2 class="section-title">{{ $label }} name</h2>
            @if ($isDefault)
                <p class="text-xs text-muted">The default folder can't be renamed.</p>
            @else
                <form method="POST" action="{{ route($plural.'.update', $group) }}" class="space-y-2">
                    @csrf
                    @method('PUT')
                    <input id="{{ $kind }}-name" type="text" name="name" value="{{ $group->name }}" aria-label="{{ $label }} name" class="input" required>
                    <button class="btn-primary btn-sm w-full">Save name</button>
                </form>
            @endif
        </section>
        <form method="POST" action="{{ route($plural.'.destroy', $group) }}" data-confirm="Delete the {{ $kind }} {{ $group->name }}?" class="glass-card space-y-2">
            @csrf
            @method('DELETE')
            <h2 class="section-title">Delete {{ $kind }}</h2>
            <p class="text-xs text-muted">{{ $kind === 'folder' ? 'Empty the folder before deleting it.' : 'Links keep working but lose this tag.' }}</p>
            <button class="btn-danger btn-sm w-full">Delete {{ $kind }}</button>
        </form>
    </aside>

    <section class="glass overflow-hidden rounded-2xl">
        <form method="GET" class="flex items-center gap-2 px-4 pb-2 pt-4">
            <h2 class="section-title flex-1">Links</h2>
            <div class="relative w-full max-w-xs">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input type="search" name="q" value="{{ $search }}" placeholder="Search links" aria-label="Search links" class="input py-1.5! pl-9 text-sm">
            </div>
        </form>
        <div class="overflow-x-auto">
            <table class="table-glass">
                <thead>
                    <tr>
                        <th>Link</th>
                        <th class="text-right">Clicks</th>
                        <th class="hidden sm:table-cell">Status</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($links as $link)
                        <tr>
                            <td class="max-w-56 sm:max-w-md">
                                <a href="{{ route('links.show', $link) }}" class="block truncate text-sm font-semibold text-ink hover:underline">/l/{{ $link->slug }}</a>
                                <p class="truncate text-xs text-muted" title="{{ $link->target_url }}">{{ $link->target_url }}</p>
                            </td>
                            <td class="text-right tabular-nums text-ink">{{ number_format($link->clicks) }}</td>
                            <td class="hidden sm:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    @if ($link->isExpired())
                                        <span class="chip-red">Expired</span>
                                    @else
                                        <span class="{{ $link->status === 'active' ? 'chip-green' : 'chip-gray' }}">{{ ucfirst($link->status) }}</span>
                                    @endif
                                    @if ($link->requiresPassword())
                                        <span class="chip-blue">Password</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-0.5">
                                    <a href="{{ route('links.edit', $link) }}" class="icon-btn h-8! w-8!" title="Edit" aria-label="Edit {{ $link->slug }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('links.destroy', $link) }}" data-confirm="Delete /{{ $link->slug }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="icon-btn h-8! w-8! hover:text-rose-500!" title="Delete" aria-label="Delete {{ $link->slug }}">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-sm text-muted">{{ $search ? 'No links match your search.' : 'No links in this '.$kind.' yet.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($links->hasPages())
            <div class="border-t border-slate-500/10 px-4 py-3">{{ $links->links() }}</div>
        @endif
    </section>
</div>
