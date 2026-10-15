<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - My links</x-slot>
    <x-page-header title="My links" subtitle="Links on your own admin account." />

    <form method="GET" class="glass-card flex flex-col gap-2 !p-3 md:flex-row">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
            <input type="search" name="q" value="{{ $search }}" placeholder="Search slug or destination" aria-label="Search links" class="input !py-2 pl-9">
        </div>
        <select name="folder_id" aria-label="Folder" class="input !py-2 md:!w-44" data-autosubmit>
            <option value="">All folders</option>
            @foreach ($folders as $folder)
                <option value="{{ $folder->id }}" @selected($folderId == $folder->id)>{{ $folder->name }}</option>
            @endforeach
        </select>
        <select name="tag_id" aria-label="Tag" class="input !py-2 md:!w-44" data-autosubmit>
            <option value="">All tags</option>
            @foreach ($tags as $tag)
                <option value="{{ $tag->id }}" @selected($tagId == $tag->id)>{{ $tag->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-primary btn-sm">Filter</button>
    </form>

    <section class="glass overflow-hidden rounded-2xl">
        <div class="overflow-x-auto">
            <table class="table-glass">
                <thead>
                    <tr>
                        <th>Link</th>
                        <th class="hidden md:table-cell">Folder · tags</th>
                        <th class="text-right">Clicks</th>
                        <th class="hidden sm:table-cell">Status</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($links as $link)
                        <tr>
                            <td class="max-w-[14rem] sm:max-w-sm">
                                <a href="{{ $link->short_url }}" target="_blank" rel="noopener" class="block truncate text-sm font-semibold text-ink hover:underline">/{{ $link->slug }}</a>
                                <p class="truncate text-xs text-muted" title="{{ $link->target_url }}">{{ $link->target_url }}</p>
                            </td>
                            <td class="hidden md:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    @if ($link->folder)
                                        <span class="chip-blue">{{ $link->folder->name }}</span>
                                    @endif
                                    @foreach ($link->tags as $tag)
                                        <span class="chip-gray">#{{ $tag->name }}</span>
                                    @endforeach
                                    @if (! $link->folder && $link->tags->isEmpty())
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-right tabular-nums text-ink">{{ number_format($link->clicks) }}</td>
                            <td class="hidden sm:table-cell"><span class="{{ $link->status === 'active' ? 'chip-green' : 'chip-red' }}">{{ ucfirst($link->status) }}</span></td>
                            <td class="whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-0.5">
                                    <a href="{{ route('links.show', $link) }}" class="icon-btn !h-8 !w-8" title="Analytics" aria-label="Analytics for {{ $link->slug }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    </a>
                                    <a href="{{ route('links.edit', $link) }}" class="icon-btn !h-8 !w-8" title="Edit" aria-label="Edit {{ $link->slug }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm text-muted">No links found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($links->hasPages())
            <div class="border-t border-slate-500/10 px-4 py-3">{{ $links->links() }}</div>
        @endif
    </section>
</x-app-layout>
