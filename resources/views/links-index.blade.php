<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - My Links</x-slot>
    <div x-data="linksManager()" class="space-y-4">
        <x-page-header title="My links" subtitle="{{ number_format($links->total()) }} short links" />

        @if (session('status'))
            <div class="glass rounded-2xl px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">{{ session('status') }}</div>
        @endif
        @if (session('shortlink'))
            @php $newLink = session('shortlink'); @endphp
            <div class="glass-card flex flex-col gap-3 sm:flex-row sm:items-center" x-data="{ copied: false }">
                <div class="min-w-0 flex-1">
                    <p class="eyebrow">Link created</p>
                    <a href="{{ $newLink->short_url }}" target="_blank" class="block truncate font-mono text-base font-semibold text-indigo-600 dark:text-indigo-300">{{ $newLink->short_url }}</a>
                </div>
                <button type="button" class="btn-primary btn-sm" @click="navigator.clipboard.writeText(@js($newLink->short_url)); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                </button>
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" class="glass flex flex-col gap-2 rounded-2xl p-2 sm:flex-row sm:items-center" x-data="{ more: {{ ($filters['folder'] || $filters['tag'] || $filters['sort'] !== 'created_desc') ? 'true' : 'false' }} }">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search links…" class="input !border-transparent !bg-transparent pl-9">
            </div>
            <div class="flex flex-wrap gap-2 sm:flex-nowrap" :class="more ? '' : 'hidden sm:flex'">
                <select name="folder" class="input sm:w-36">
                    <option value="">All folders</option>
                    @foreach ($folders as $folder)
                        <option value="{{ $folder->id }}" @selected($filters['folder'] == $folder->id)>{{ $folder->name }}</option>
                    @endforeach
                </select>
                <select name="tag" class="input sm:w-32">
                    <option value="">All tags</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" @selected($filters['tag'] == $tag->id)>{{ $tag->name }}</option>
                    @endforeach
                </select>
                <select name="sort" class="input sm:w-36">
                    <option value="created_desc" @selected($filters['sort'] == 'created_desc')>Newest</option>
                    <option value="created_asc" @selected($filters['sort'] == 'created_asc')>Oldest</option>
                    <option value="clicks_desc" @selected($filters['sort'] == 'clicks_desc')>Most clicks</option>
                    <option value="clicks_asc" @selected($filters['sort'] == 'clicks_asc')>Least clicks</option>
                    <option value="slug_asc" @selected($filters['sort'] == 'slug_asc')>A–Z</option>
                    <option value="slug_desc" @selected($filters['sort'] == 'slug_desc')>Z–A</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn-ghost btn-sm sm:hidden" @click="more = !more">Filters</button>
                <button class="btn-soft btn-sm flex-1 sm:flex-none">Apply</button>
            </div>
        </form>

        {{-- Bulk actions --}}
        <div x-show="selectedLinks.length > 0" x-cloak x-transition class="glass sticky top-20 z-20 flex items-center gap-3 rounded-2xl px-4 py-2.5">
            <span class="text-sm font-medium text-ink" x-text="selectedLinks.length + ' selected'"></span>
            <button @click="bulkDelete()" class="btn-danger btn-sm ml-auto">Delete</button>
            <button @click="selectedLinks = []; selectAll = false" class="btn-ghost btn-sm">Clear</button>
        </div>

        <section class="glass overflow-hidden rounded-2xl">
            @if ($links->count())
                <div class="hidden items-center gap-3 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted md:flex">
                    <input type="checkbox" x-model="selectAll" @change="toggleSelectAll()" class="rounded border-slate-400/50 bg-transparent text-indigo-500">
                    <span class="flex-1">Link</span>
                    <span class="w-20 text-right">Clicks</span>
                    <span class="w-24 text-right">Created</span>
                    <span class="w-28"></span>
                </div>
            @endif
            <div class="divide-glass">
                @forelse ($links as $link)
                    <div class="list-row flex-wrap md:flex-nowrap" x-show="!deletedLinks.includes({{ $link->id }})">
                        <input type="checkbox" value="{{ $link->id }}" x-model="selectedLinks" class="rounded border-slate-400/50 bg-transparent text-indigo-500">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <a href="{{ route('links.show', $link) }}" class="truncate text-sm font-semibold text-ink hover:text-indigo-600 dark:hover:text-indigo-300">/{{ $link->slug }}</a>
                                @if ($link->isExpired())
                                    <span class="chip-red">Expired</span>
                                @elseif ($link->status !== 'active')
                                    <span class="chip-gray">{{ ucfirst($link->status) }}</span>
                                @endif
                                @if ($link->requiresPassword())
                                    <span class="chip-blue" title="Password protected">🔒</span>
                                @endif
                                @foreach ($link->tags as $tag)
                                    <span class="chip-gray">{{ $tag->name }}</span>
                                @endforeach
                            </div>
                            <p class="truncate text-xs text-muted">{{ $link->target_url }}</p>
                            @if ($link->folder)
                                <p class="mt-0.5 text-[11px] text-muted">📁 {{ $link->folder->name }}</p>
                            @endif
                        </div>
                        <div class="w-20 text-right">
                            <p class="text-sm font-semibold tabular-nums text-ink">{{ number_format($link->clicks) }}</p>
                            <p class="text-[11px] text-muted md:hidden">clicks</p>
                        </div>
                        <p class="hidden w-24 text-right text-xs text-muted md:block" title="{{ $link->created_at }}">{{ $link->created_at->format('M d, Y') }}</p>
                        <div class="flex w-full justify-end md:w-28">
                            <button type="button" class="icon-btn" title="Copy" x-data @click="navigator.clipboard.writeText(@js($link->short_url)); $el.classList.add('!text-emerald-500'); setTimeout(() => $el.classList.remove('!text-emerald-500'), 1200)">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/></svg>
                            </button>
                            <a href="{{ route('links.show', $link) }}" class="icon-btn" title="Analytics">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V10m6 10V4m6 16v-7"/></svg>
                            </a>
                            <a href="{{ route('links.edit', $link) }}" class="icon-btn" title="Edit">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m15 5 4 4L8 20H4v-4L15 5Z"/></svg>
                            </a>
                            <button @click="deleteLink({{ $link->id }})" class="icon-btn hover:!text-rose-500" title="Delete">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-14 text-center">
                        <p class="text-sm text-muted">{{ $filters['q'] ? 'No links match your search.' : 'No links yet.' }}</p>
                        <button type="button" onclick="window.openLinkPanel && window.openLinkPanel()" class="btn-primary btn-sm mt-3">Create a link</button>
                    </div>
                @endforelse
            </div>
        </section>
        <div>{{ $links->links() }}</div>
    </div>
    <script>
        function linksManager() {
            return {
                selectedLinks: [],
                selectAll: false,
                deletedLinks: [],
                linkIds: @json($links->pluck('id')->toArray()),
                toggleSelectAll() {
                    if (this.selectAll) {
                        this.selectedLinks = this.linkIds.filter(id => !this.deletedLinks.includes(id)).map(String);
                    } else {
                        this.selectedLinks = [];
                    }
                },
                async deleteLink(id) {
                    if (!confirm('Delete this link permanently?')) return;
                    try {
                        const response = await fetch(`/links/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            }
                        });
                        if (response.ok) {
                            this.deletedLinks.push(id);
                            this.selectedLinks = this.selectedLinks.filter(lid => lid != id);
                        } else {
                            alert('Failed to delete link');
                        }
                    } catch (e) {
                        console.error('Delete error:', e);
                        alert('Failed to delete link');
                    }
                },
                async bulkDelete() {
                    if (!confirm(`Delete ${this.selectedLinks.length} links permanently?`)) return;
                    for (const id of this.selectedLinks) {
                        try {
                            await fetch(`/links/${id}`, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                }
                            });
                            this.deletedLinks.push(parseInt(id));
                        } catch (e) {
                            console.error('Delete error for', id, e);
                        }
                    }
                    this.selectedLinks = [];
                    this.selectAll = false;
                }
            };
        }
    </script>
    {{-- Link Creation Modal Component --}}
    <x-link-creation-modal :folders="$folders" :tags="$tags" :isAdmin="auth()->user()->isSuperAdmin()" />
</x-app-layout>
