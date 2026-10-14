{{--
    Shared folders / tags overview.
    Expects: $kind ('folder'|'tag'), $groups (paginator with links_count, links_sum_clicks and up to 3 recent links).
--}}
@php
    $plural = $kind.'s';
    $label = ucfirst($kind);
@endphp

{{-- The top bar already carries the "New {{ $kind }}" button. --}}
<x-page-header :title="ucfirst($plural)" :subtitle="$kind === 'folder' ? 'Group shortlinks by campaign or project.' : 'Label shortlinks for faster filtering.'" />

<x-flash />

@if ($groups->isEmpty())
    <div class="glass-card py-12 text-center">
        <p class="text-sm text-muted">No {{ $plural }} yet.</p>
        <button type="button" class="btn-soft btn-sm mt-3" x-data @click="$dispatch('open-modal', 'create-{{ $kind }}')">Create your first {{ $kind }}</button>
    </div>
@else
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($groups as $group)
            @php $isDefault = $kind === 'folder' && $group->is_default; @endphp
            <section id="{{ $kind }}-{{ $group->id }}" class="glass-card flex flex-col gap-3" x-data="{ renaming: false }">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-600 dark:text-indigo-300">
                        @if ($kind === 'folder')
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                        @else
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <div x-show="!renaming" class="flex items-center gap-1.5">
                            <h2 class="truncate text-sm font-semibold text-ink">{{ $group->name }}</h2>
                            @if ($isDefault)
                                <span class="chip-gray">Default</span>
                            @endif
                        </div>
                        @unless ($isDefault)
                            <form x-show="renaming" x-cloak method="POST" action="{{ route($plural.'.update', $group) }}" class="flex gap-1.5">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $group->name }}" aria-label="{{ $label }} name" class="input !py-1 text-sm" required>
                                <button class="btn-primary btn-sm">Save</button>
                                <button type="button" class="btn-ghost btn-sm" @click="renaming = false">Cancel</button>
                            </form>
                        @endunless
                        <p class="text-xs text-muted">{{ number_format($group->links_count) }} {{ Str::plural('link', $group->links_count) }} · {{ number_format((int) $group->links_sum_clicks) }} clicks</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-0.5" x-show="!renaming">
                        @unless ($isDefault)
                            <button type="button" class="icon-btn !h-8 !w-8" title="Rename" aria-label="Rename {{ $group->name }}" @click="renaming = true">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                        @endunless
                        <form method="POST" action="{{ route($plural.'.destroy', $group) }}" data-confirm="Delete the {{ $kind }} {{ $group->name }}?">
                            @csrf
                            @method('DELETE')
                            <button class="icon-btn !h-8 !w-8 hover:!text-rose-500" title="Delete" aria-label="Delete {{ $group->name }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>

                <ul class="glass-inset divide-glass flex-1 rounded-xl">
                    @forelse ($group->links as $link)
                        <li class="flex items-center justify-between gap-2 px-3 py-1.5">
                            <a href="{{ route('links.show', $link) }}" class="min-w-0">
                                <span class="block truncate text-xs font-medium text-ink hover:underline">/{{ $link->slug }}</span>
                                <span class="block truncate text-[11px] text-muted">{{ $link->target_url }}</span>
                            </a>
                            <span class="shrink-0 text-[11px] tabular-nums text-muted">{{ number_format($link->clicks) }}</span>
                        </li>
                    @empty
                        <li class="px-3 py-4 text-center text-xs text-muted">No links yet.</li>
                    @endforelse
                </ul>

                <a href="{{ route($plural.'.manage', $group) }}" class="btn-soft btn-sm justify-center">Open {{ $kind }}</a>
            </section>
        @endforeach
    </div>

    @if ($groups->hasPages())
        <div>{{ $groups->links() }}</div>
    @endif
@endif

{{-- The top bar's "New {{ $kind }}" button calls window.open{{ $label }}Modal. --}}
<div x-data x-init="window.open{{ $label }}Modal = () => $dispatch('open-modal', 'create-{{ $kind }}')"></div>
<x-modal name="create-{{ $kind }}" :show="$errors->has('name')" maxWidth="md" focusable>
    <form method="POST" action="{{ route($plural.'.store') }}" class="space-y-4 p-5">
        @csrf
        <h2 class="section-title text-base">New {{ $kind }}</h2>
        <div>
            <label for="{{ $kind }}-name" class="label">Name</label>
            <input type="text" id="{{ $kind }}-name" name="name" value="{{ old('name') }}" placeholder="{{ $kind === 'folder' ? 'e.g. Marketing campaign' : 'e.g. social' }}" class="input" required>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" class="btn-ghost btn-sm" @click="$dispatch('close-modal', 'create-{{ $kind }}')">Cancel</button>
            <button type="submit" class="btn-primary btn-sm">Create {{ $kind }}</button>
        </div>
    </form>
</x-modal>
