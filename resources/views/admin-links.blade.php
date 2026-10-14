<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Global links</x-slot>
    @php
        $activeFilters = collect([$search, $status, $filters['user_id'], $filters['tag'], $filters['guest']])->filter()->count();
    @endphp

    <x-page-header title="Global links" subtitle="Every short link on the platform.">
        <x-slot name="actions">
            <span class="text-sm text-muted">{{ number_format($links->total()) }} links</span>
        </x-slot>
    </x-page-header>

    <x-flash />

    <form method="GET" class="glass-card !p-3">
        <div class="grid gap-2 md:grid-cols-12">
            <div class="relative md:col-span-4">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input type="search" name="q" value="{{ $search }}" placeholder="Slug or destination" aria-label="Search links" class="input pl-9">
            </div>
            <div class="md:col-span-3">
                <x-admin.user-picker :selected="$owner" placeholder="Owner: name or email" />
            </div>
            <div class="md:col-span-2">
                <input type="search" name="tag" value="{{ $filters['tag'] }}" placeholder="#tag" aria-label="Tag" class="input">
            </div>
            <select name="status" aria-label="Status" class="input md:col-span-2" data-autosubmit>
                <option value="">Any status</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="expired" @selected($status === 'expired')>Expired</option>
                <option value="inactive" @selected($status === 'inactive')>Disabled</option>
            </select>
            <button class="btn-primary md:col-span-1">Filter</button>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
            @if ($owner && $folders->isNotEmpty())
                <select name="folder_id" aria-label="Folder" class="input !w-auto !py-1.5 text-xs" data-autosubmit>
                    <option value="">All folders of {{ \Illuminate\Support\Str::limit($owner->name, 20) }}</option>
                    @foreach ($folders as $folder)
                        <option value="{{ $folder->id }}" @selected($filters['folder_id'] == $folder->id)>{{ $folder->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="sort" aria-label="Sort" class="input !w-auto !py-1.5 text-xs" data-autosubmit>
                <option value="">Newest first</option>
                <option value="clicks" @selected($filters['sort'] === 'clicks')>Most clicks</option>
            </select>
            <label class="inline-flex items-center gap-1.5 text-muted">
                <input type="checkbox" name="owner" value="guest" @checked($filters['guest']) data-autosubmit class="rounded border-slate-400/50 bg-transparent text-indigo-500">
                Guest links only
            </label>
            @if ($activeFilters)
                <a href="{{ route('admin.links.index') }}" class="ml-auto font-medium text-indigo-600 hover:underline dark:text-indigo-300">Clear filters ({{ $activeFilters }})</a>
            @endif
        </div>
    </form>

    <section class="glass overflow-hidden rounded-2xl" x-data="{ count: 0, sync() { this.count = this.$root.querySelectorAll('.link-check:checked').length } }">
        <form method="POST" action="{{ route('admin.links.bulk') }}" id="bulk-form" @change="sync()">
            @csrf
            <div class="flex flex-wrap items-center gap-2 px-4 py-3" x-show="count > 0" x-cloak>
                <span class="text-sm font-medium text-ink"><span x-text="count"></span> selected</span>
                <button type="submit" name="action" value="disable" class="btn-soft btn-sm" data-confirm="Disable the selected links?">Disable</button>
                <button type="submit" name="action" value="delete" class="btn-danger btn-sm" data-confirm="Delete the selected links permanently?">Delete</button>
            </div>
            <div class="overflow-x-auto">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th class="w-8">
                                <input type="checkbox" aria-label="Select all links" @click="$root.querySelectorAll('.link-check').forEach(cb => cb.checked = $event.target.checked); sync()" class="rounded border-slate-400/50 bg-transparent text-indigo-500">
                            </th>
                            <th>Link</th>
                            <th class="hidden md:table-cell">Owner</th>
                            <th class="hidden lg:table-cell">Folder / tags</th>
                            <th class="text-right">Clicks</th>
                            <th>Status</th>
                            <th class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($links as $link)
                            <tr>
                                <td>
                                    <input type="checkbox" aria-label="Select {{ $link->slug }}" name="ids[]" value="{{ $link->id }}" class="link-check rounded border-slate-400/50 bg-transparent text-indigo-500">
                                </td>
                                <td class="max-w-xs">
                                    <a href="{{ $link->short_url }}" target="_blank" rel="noopener" class="font-mono text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-300">/{{ $link->slug }}</a>
                                    <p class="truncate text-xs text-muted" title="{{ $link->target_url }}">{{ $link->target_url }}</p>
                                </td>
                                <td class="hidden max-w-[14rem] md:table-cell">
                                    @if ($link->user)
                                        <a href="{{ route('admin.users.inspect', $link->user) }}" class="block truncate text-sm text-ink hover:underline">{{ $link->user->name }}</a>
                                        <p class="truncate text-xs text-muted">{{ $link->user->email }}</p>
                                    @else
                                        <span class="chip-gray">Guest</span>
                                    @endif
                                </td>
                                <td class="hidden lg:table-cell">
                                    <div class="flex max-w-[16rem] flex-wrap gap-1">
                                        @if ($link->folder)
                                            <span class="chip-blue">{{ $link->folder->name }}</span>
                                        @endif
                                        @foreach ($link->tags as $tag)
                                            <span class="chip-gray">#{{ $tag->name }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-right tabular-nums text-ink">{{ number_format($link->clicks) }}</td>
                                <td>
                                    @if ($link->status !== 'active')
                                        <span class="chip-red">Disabled</span>
                                    @elseif ($link->isExpired())
                                        <span class="chip-amber">Expired</span>
                                    @else
                                        <span class="chip-green">Active</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-0.5">
                                        <a href="{{ route('links.edit', $link) }}" class="icon-btn !h-8 !w-8" title="Edit" aria-label="Edit {{ $link->slug }}">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.232-6.232a2.5 2.5 0 113.536 3.536L12.536 16.536 9 17l.464-3.536z"/></svg>
                                        </a>
                                        <button type="submit" form="status-{{ $link->id }}" class="icon-btn !h-8 !w-8" title="{{ $link->status === 'active' ? 'Disable' : 'Enable' }}" aria-label="{{ $link->status === 'active' ? 'Disable' : 'Enable' }} {{ $link->slug }}">
                                            @if ($link->status === 'active')
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                            @else
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </button>
                                        <button type="submit" form="delete-{{ $link->id }}" class="icon-btn !h-8 !w-8 hover:!text-rose-500" title="Delete" aria-label="Delete {{ $link->slug }}">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-sm text-muted">No links match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        @if ($links->hasPages())
            <div class="border-t border-slate-500/10 px-4 py-3">{{ $links->links() }}</div>
        @endif
    </section>

    {{-- Row action forms live outside the bulk form (forms cannot nest); buttons reach them via form="…". --}}
    @foreach ($links as $link)
        <form id="status-{{ $link->id }}" method="POST" action="{{ route('admin.links.status', $link) }}" class="hidden" data-confirm="{{ $link->status === 'active' ? 'Disable' : 'Enable' }} /{{ $link->slug }}?">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $link->status === 'active' ? 'inactive' : 'active' }}">
        </form>
        <form id="delete-{{ $link->id }}" method="POST" action="{{ route('admin.links.destroy', $link) }}" class="hidden" data-confirm="Delete /{{ $link->slug }} permanently?">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</x-app-layout>
