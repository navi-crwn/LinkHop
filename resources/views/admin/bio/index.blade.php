<x-app-layout>
    <x-slot name="pageTitle">LinkHop - Bio pages</x-slot>
    <x-page-header title="Bio pages" subtitle="Every link-in-bio page on the platform.">
        <x-slot name="actions">
            <a href="{{ route('admin.bio.monitor') }}" class="btn-soft btn-sm">Monitoring</a>
        </x-slot>
    </x-page-header>

    <x-flash />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="stat"><p class="stat-label">Bio pages</p><p class="stat-value">{{ number_format($stats['total']) }}</p></div>
        <div class="stat"><p class="stat-label">Published</p><p class="stat-value text-emerald-600 dark:text-emerald-400">{{ number_format($stats['published']) }}</p></div>
        <div class="stat"><p class="stat-label">Draft</p><p class="stat-value">{{ number_format($stats['draft']) }}</p></div>
        <div class="stat"><p class="stat-label">Views</p><p class="stat-value">{{ number_format($stats['total_views']) }}</p></div>
    </div>

    <form method="GET" class="glass-card flex flex-col gap-2 p-3! md:flex-row">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search slug, title or user" aria-label="Search bio pages" class="input py-2! pl-9">
        </div>
        <select name="status" aria-label="Filter by status" class="input py-2! md:w-40!" data-autosubmit>
            <option value="">All statuses</option>
            <option value="published" @selected(request('status') === 'published')>Published</option>
            <option value="draft" @selected(request('status') === 'draft')>Draft</option>
        </select>
        <button type="submit" class="btn-primary btn-sm">Filter</button>
        @if (request('search') || request('status'))
            <a href="{{ route('admin.bio.index') }}" class="btn-ghost btn-sm">Clear</a>
        @endif
    </form>

    <section class="glass overflow-hidden rounded-2xl">
        <div class="overflow-x-auto">
            <table class="table-glass">
                <thead>
                    <tr>
                        <th>Bio page</th>
                        <th class="hidden md:table-cell">Owner</th>
                        <th class="hidden text-right sm:table-cell">Views</th>
                        <th class="hidden text-right sm:table-cell">Links</th>
                        <th class="hidden sm:table-cell">Status</th>
                        <th class="hidden lg:table-cell">Created</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bioPages as $bioPage)
                        <tr>
                            <td class="max-w-56 sm:max-w-xs">
                                <a href="{{ route('admin.bio.show', $bioPage) }}" class="flex items-center gap-3">
                                    @if ($bioPage->avatar_url && Storage::disk('public')->exists($bioPage->avatar_url))
                                        <img src="{{ Storage::disk('public')->url($bioPage->avatar_url) }}" class="h-8 w-8 shrink-0 rounded-xl object-cover" alt="">
                                    @else
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-xs font-semibold text-indigo-600 dark:text-indigo-300">{{ strtoupper(mb_substr($bioPage->title, 0, 1)) }}</span>
                                    @endif
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <span class="truncate text-sm font-semibold text-ink">{{ $bioPage->title }}</span>
                                            @unless ($bioPage->is_published)
                                                <span class="chip-gray sm:hidden">Draft</span>
                                            @endunless
                                        </span>
                                        <span class="block truncate text-xs text-muted">/b/{{ $bioPage->slug }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="hidden max-w-48 md:table-cell">
                                @if ($bioPage->user)
                                    <a href="{{ route('admin.users.inspect', $bioPage->user) }}" class="block truncate text-sm text-ink hover:underline">{{ $bioPage->user->name }}</a>
                                    <p class="truncate text-xs text-muted">{{ $bioPage->user->email }}</p>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="hidden text-right tabular-nums text-ink sm:table-cell">{{ number_format($bioPage->view_count) }}</td>
                            <td class="hidden text-right tabular-nums text-ink sm:table-cell">{{ number_format($bioPage->links_count) }}</td>
                            <td class="hidden sm:table-cell"><span class="{{ $bioPage->is_published ? 'chip-green' : 'chip-gray' }}">{{ $bioPage->is_published ? 'Published' : 'Draft' }}</span></td>
                            <td class="hidden whitespace-nowrap text-xs text-muted lg:table-cell">{{ $bioPage->created_at->format('d M Y') }}</td>
                            <td class="whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-0.5">
                                    <a href="{{ route('bio.public.show', $bioPage->slug) }}" target="_blank" rel="noopener" class="icon-btn h-8! w-8!" title="Open public page" aria-label="Open {{ $bioPage->slug }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                    <form action="{{ route('admin.bio.toggle', $bioPage) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="icon-btn h-8! w-8!" title="{{ $bioPage->is_published ? 'Unpublish' : 'Publish' }}" aria-label="{{ $bioPage->is_published ? 'Unpublish' : 'Publish' }} {{ $bioPage->slug }}">
                                            @if ($bioPage->is_published)
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                            @else
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.bio.destroy', $bioPage) }}" method="POST" data-confirm="Delete the bio page /b/{{ $bioPage->slug }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn h-8! w-8! hover:text-rose-500!" title="Delete" aria-label="Delete {{ $bioPage->slug }}">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-sm text-muted">No bio pages found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bioPages->hasPages())
            <div class="border-t border-slate-500/10 px-4 py-3">{{ $bioPages->links() }}</div>
        @endif
    </section>
</x-app-layout>
