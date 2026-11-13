<x-app-layout>
    <x-slot name="pageTitle">{{ __('My links') }}</x-slot>
    <div x-data="linksManager()" class="space-y-4">
        <x-page-header :title="__('My links')">
            {{-- Kept current by the script after a delete, so it never says "4 short links" above 3 rows. --}}
            <x-slot name="sub"><span class="tabular-nums" x-text="countLabel()">{{ $links->total() === 1 ? __('1 short link') : __(':count short links', ['count' => number_format($links->total())]) }}</span></x-slot>
        </x-page-header>

        @if (session('status'))
            <div class="glass rounded-2xl px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">{{ session('status') }}</div>
        @endif
        <x-claim-notice />
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
            {{-- The search field is 20rem wide from 640px up; the filters keep to the right of it. --}}
            <div class="search sm:mr-auto">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search links…') }}" aria-label="{{ __('Search links…') }}" class="input">
            </div>
            <div class="flex flex-wrap gap-2 sm:flex-nowrap" :class="more ? '' : 'hidden sm:flex'">
                <select name="folder" class="input sm:w-36" aria-label="Filter by folder">
                    <option value="">All folders</option>
                    @foreach ($folders as $folder)
                        <option value="{{ $folder->id }}" @selected($filters['folder'] == $folder->id)>{{ $folder->name }}</option>
                    @endforeach
                </select>
                <select name="tag" class="input sm:w-32" aria-label="Filter by tag">
                    <option value="">All tags</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" @selected($filters['tag'] == $tag->id)>{{ $tag->name }}</option>
                    @endforeach
                </select>
                <select name="sort" class="input sm:w-36" aria-label="Sort links">
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
                <button class="btn-soft btn-sm max-sm:flex-1">Apply</button>
            </div>
        </form>

        {{-- Bulk actions --}}
        <div x-show="selectedLinks.length > 0" x-cloak x-transition class="glass sticky top-20 z-20 flex items-center gap-3 rounded-2xl px-4 py-2.5">
            <span class="text-sm font-medium tabular-nums text-ink" x-text="@js(__(':count selected')).replace(':count', selectedLinks.length)"></span>
            <button type="button" @click="bulkDelete($el)" class="btn-danger btn-sm ml-auto">{{ __('Delete') }}</button>
            <button type="button" @click="selectedLinks = []; selectAll = false" class="btn-ghost btn-sm">{{ __('Clear') }}</button>
        </div>

        <section class="glass overflow-hidden rounded-2xl">
            @if ($links->count())
                <div class="hidden items-center gap-3 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted md:flex">
                    <input type="checkbox" aria-label="Select all links" x-model="selectAll" @change="toggleSelectAll()" class="border-slate-400/50 bg-transparent text-indigo-500">
                    <span class="flex-1">Link</span>
                    <span class="w-20 text-right">Clicks</span>
                    <span class="w-24 text-right">Created</span>
                    <span class="w-28"></span>
                </div>
            @endif
            <div class="divide-glass">
                @forelse ($links as $link)
                    <div class="list-row flex-wrap md:flex-nowrap" x-show="!deletedLinks.includes('{{ $link->id }}')">
                        <input type="checkbox" aria-label="{{ __('Select :slug', ['slug' => $link->slug]) }}" value="{{ $link->id }}" x-model="selectedLinks" class="border-slate-400/50 bg-transparent text-indigo-500">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <a href="{{ route('links.show', $link) }}" class="truncate text-sm font-semibold text-ink hover:text-indigo-600 dark:hover:text-indigo-300">/l/{{ $link->slug }}</a>
                                @if ($link->isExpired())
                                    <span class="chip-red">Expired</span>
                                @elseif ($link->status !== 'active')
                                    <span class="chip-gray">{{ ucfirst($link->status) }}</span>
                                @endif
                                @if ($link->expires_at && ! $link->isExpired())
                                    <span class="chip-gray whitespace-nowrap" title="{{ $link->expires_at }}">{{ __('Ends in :when', ['when' => $link->expires_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true)]) }}</span>
                                @endif
                                @if ($link->isNotStarted())
                                    <span class="chip-gray whitespace-nowrap" title="{{ $link->starts_at }}">{{ __('Starts in :when', ['when' => $link->starts_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true)]) }}</span>
                                @endif
                                @if ($link->isLimitReached())
                                    <span class="chip-red whitespace-nowrap">{{ __('Limit reached') }}</span>
                                @elseif ($link->max_clicks)
                                    <span class="chip-gray whitespace-nowrap">{{ number_format($link->clicks) }}/{{ number_format($link->max_clicks) }}</span>
                                @endif
                                @if ($link->routing_rules)
                                    <span class="chip-blue whitespace-nowrap" title="{{ __('Routing rules') }}">{{ count($link->routing_rules) === 1 ? __('1 rule') : __(':count rules', ['count' => count($link->routing_rules)]) }}</span>
                                @endif
                                @if ($link->ab_variants)
                                    <span class="chip-blue whitespace-nowrap" title="{{ __('A/B split') }}">A/B</span>
                                @endif
                                @if ($link->wa_mode)
                                    <span class="chip-blue whitespace-nowrap" title="{{ __('WhatsApp rotator') }}">WhatsApp</span>
                                @endif
                                @if ($link->app_links)
                                    <span class="chip-blue whitespace-nowrap" title="{{ __('Open in the app') }}">{{ __('App') }}</span>
                                @endif
                                @if ($link->isBroken())
                                    <span class="chip-amber whitespace-nowrap" title="{{ __('Since :date', ['date' => $link->broken_since->toDateString()]) }}{{ $link->last_status ? ' · HTTP '.$link->last_status : '' }}">{{ __('Destination not responding') }}</span>
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
                        <x-spark :values="$sparks[$link->id] ?? []" class="hidden lg:block" />
                        <div class="w-20 text-right">
                            <p class="text-sm font-semibold tabular-nums text-ink">{{ number_format($link->clicks) }}</p>
                            <p class="text-[11px] text-muted md:hidden">clicks</p>
                        </div>
                        <p class="hidden w-24 text-right text-xs text-muted md:block" title="{{ $link->created_at }}">{{ $link->created_at->format('M d, Y') }}</p>
                        <div class="flex w-full justify-end md:w-28">
                            <button type="button" class="icon-btn" title="{{ __('Copy link') }}" aria-label="{{ __('Copy link') }}" data-copied="{{ __('Copied') }}" data-failed="{{ __('Copy failed') }}" x-data @click="hopCopy(@js($link->short_url), $el)">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/></svg>
                            </button>
                            <a href="{{ route('links.show', $link) }}" class="icon-btn" title="Analytics">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V10m6 10V4m6 16v-7"/></svg>
                            </a>
                            <a href="{{ route('links.edit', $link) }}" class="icon-btn" title="Edit">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m15 5 4 4L8 20H4v-4L15 5Z"/></svg>
                            </a>
                            <button type="button" @click="deleteLink('{{ $link->id }}', @js('/l/'.$link->slug), $el)" class="icon-btn hover:text-rose-500!" title="{{ __('Delete') }}" aria-label="{{ __('Delete :slug', ['slug' => $link->slug]) }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-14 text-center">
                        <p class="text-sm text-muted">{{ $filters['q'] ? __('No links match your search.') : __('No links yet.') }}</p>
                        <button type="button" data-call="openLinkPanel" class="btn-primary btn-sm mt-3">{{ __('Create a link') }}</button>
                    </div>
                @endforelse
            </div>
        </section>
        <div>{{ $links->links() }}</div>
    </div>
    <script nonce="{{ Vite::cspNonce() }}">
        // Ids are strings throughout (checkbox values are strings), so a deleted link is found again whatever made it.
        function linksManager() {
            return {
                selectedLinks: [],
                selectAll: false,
                deletedLinks: [],
                linkIds: @json($links->pluck('id')->map(fn ($id) => (string) $id)->values()->all()),
                total: {{ (int) $links->total() }},
                countLabel() {
                    const n = Math.max(0, this.total - this.deletedLinks.length);
                    return n === 1 ? @js(__('1 short link')) : @js(__(':count short links')).replace(':count', n.toLocaleString());
                },
                toggleSelectAll() {
                    if (this.selectAll) {
                        this.selectedLinks = this.linkIds.filter(id => !this.deletedLinks.includes(id));
                    } else {
                        this.selectedLinks = [];
                    }
                },
                // The server has no row any more: drop it from the list, the selection and the count.
                forget(id) {
                    id = String(id);
                    if (!this.deletedLinks.includes(id)) {
                        this.deletedLinks.push(id);
                    }
                    this.selectedLinks = this.selectedLinks.filter(lid => String(lid) !== id);
                    if (this.selectedLinks.length === 0) {
                        this.selectAll = false;
                    }
                },
                async remove(id) {
                    const response = await fetch(`/links/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });
                    // 404: it was deleted already (another tab, or an earlier attempt that did not answer): gone either way.
                    return response.ok || response.status === 404;
                },
                // The family dialog (hop-ui) when the page has it, the browser's own otherwise: see resources/js/dialogs.js.
                failed(title) {
                    return window.hopDialog.alert({ title, message: @js(__('Nothing else was changed. Try again in a moment.')) });
                },
                async deleteLink(id, name, button) {
                    const yes = await window.hopDialog.confirm({
                        title: @js(__('Delete this link permanently?')),
                        message: @js(__('The short link stops working and its statistics are removed. This cannot be undone.')),
                        danger: true,
                        subject: name,
                        anchor: button,
                    });
                    if (!yes) return;
                    try {
                        if (await this.remove(id)) {
                            this.forget(id);
                        } else {
                            this.failed(@js(__('Failed to delete link')));
                        }
                    } catch (e) {
                        console.error('Delete error:', e);
                        this.failed(@js(__('Failed to delete link')));
                    }
                },
                async bulkDelete(button) {
                    const ids = this.selectedLinks.map(String).filter(id => !this.deletedLinks.includes(id));
                    if (ids.length === 0) {
                        this.selectedLinks = [];
                        this.selectAll = false;
                        return;
                    }
                    const question = ids.length === 1 ? @js(__('Delete 1 link permanently?')) : @js(__('Delete :count links permanently?')).replace(':count', ids.length);
                    const yes = await window.hopDialog.confirm({
                        title: question,
                        message: ids.length === 1
                            ? @js(__('The short link stops working and its statistics are removed. This cannot be undone.'))
                            : @js(__('The short links stop working and their statistics are removed. This cannot be undone.')),
                        danger: true,
                        anchor: button,
                    });
                    if (!yes) return;
                    let failed = 0;
                    for (const id of ids) {
                        try {
                            if (await this.remove(id)) {
                                this.forget(id);
                            } else {
                                failed++;
                            }
                        } catch (e) {
                            console.error('Delete error for', id, e);
                            failed++;
                        }
                    }
                    if (failed > 0) {
                        this.failed(failed === 1 ? @js(__('Could not delete 1 link.')) : @js(__('Could not delete :count links.')).replace(':count', failed));
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
