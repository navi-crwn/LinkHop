<x-app-layout>
    <x-slot name="pageTitle">Inspect User - {{ $user->name }}</x-slot>
    @php
        $isActive = $user->status === \App\Models\User::STATUS_ACTIVE;
        $roleChip = $user->isSuperAdmin() ? 'chip-amber' : ($user->isAdmin() ? 'chip-blue' : 'chip-gray');
    @endphp

    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.users.index') }}" class="icon-btn" aria-label="Back to users">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-500/15 text-base font-semibold text-indigo-600 dark:text-indigo-300">
            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
        </div>
        <div class="min-w-0 flex-1">
            <h1 class="page-title truncate">{{ $user->name }}</h1>
            <p class="truncate text-sm text-muted">{{ $user->email }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @unless ($user->isSuperAdmin())
                <form method="POST" action="{{ route('admin.users.status', $user) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $isActive ? \App\Models\User::STATUS_SUSPENDED : \App\Models\User::STATUS_ACTIVE }}">
                    <button type="submit" @if ($isActive) data-confirm="Suspend this user account?" @endif class="btn-soft btn-sm">
                        {{ $isActive ? 'Suspend' : 'Activate' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" data-confirm="Permanently delete this user and all their data?" class="btn-danger btn-sm">Delete</button>
                </form>
            @endunless
        </div>
    </div>

    <x-flash />

    {{-- Bento: metrics + account --}}
    <div class="grid gap-3 lg:grid-cols-4">
        <div class="grid grid-cols-2 gap-3 lg:col-span-2">
            <div class="stat">
                <p class="stat-label">Links</p>
                <p class="stat-value">{{ number_format($user->links_count) }}</p>
                <p class="text-[11px] text-muted">{{ number_format($activeLinks) }} active</p>
            </div>
            <div class="stat">
                <p class="stat-label">Clicks</p>
                <p class="stat-value">{{ number_format($totalClicks) }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Folders</p>
                <p class="stat-value">{{ number_format($user->folders_count) }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Tags</p>
                <p class="stat-value">{{ number_format($user->tags_count) }}</p>
            </div>
        </div>
        <section class="glass-card lg:col-span-2">
            <h2 class="section-title">Account</h2>
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs text-muted">Role</dt>
                    <dd class="mt-0.5"><span class="{{ $roleChip }}">{{ ucfirst($user->role) }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Status</dt>
                    <dd class="mt-0.5"><span class="{{ $isActive ? 'chip-green' : 'chip-red' }}">{{ ucfirst($user->status) }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Member since</dt>
                    <dd class="mt-0.5 font-medium text-ink">{{ $user->created_at?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Last login</dt>
                    <dd class="mt-0.5 font-medium text-ink">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
                </div>
                @if ($user->last_login_ip)
                    <div class="col-span-2">
                        <dt class="text-xs text-muted">Last login from</dt>
                        <dd class="mt-0.5 truncate font-mono text-xs text-ink">
                            {{ $user->last_login_ip }}{{ $user->last_login_country ? ' · '.$user->last_login_country : '' }}{{ $user->last_login_browser ? ' · '.$user->last_login_browser : '' }}
                        </dd>
                    </div>
                @endif
            </dl>
        </section>
    </div>

    {{-- Links --}}
    <section class="glass overflow-hidden rounded-2xl">
        <div class="flex items-center justify-between px-4 pb-2 pt-4">
            <h2 class="section-title">Links</h2>
            <span class="text-xs text-muted">{{ number_format($links->total()) }} total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table-glass">
                <thead>
                    <tr>
                        <th>Short link</th>
                        <th>Destination</th>
                        <th class="hidden md:table-cell">Folder / tags</th>
                        <th class="text-right">Clicks</th>
                        <th>Status</th>
                        <th class="hidden sm:table-cell">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($links as $link)
                        <tr>
                            <td class="whitespace-nowrap">
                                <a href="{{ $link->short_url }}" target="_blank" rel="noopener" class="font-mono text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-300">/{{ $link->slug }}</a>
                            </td>
                            <td class="max-w-xs">
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
                                </div>
                            </td>
                            <td class="text-right tabular-nums text-ink">{{ number_format($link->clicks) }}</td>
                            <td>
                                @if ($link->isExpired())
                                    <span class="chip-amber">Expired</span>
                                @else
                                    <span class="{{ $link->status === 'active' ? 'chip-green' : 'chip-red' }}">{{ ucfirst($link->status) }}</span>
                                @endif
                            </td>
                            <td class="hidden whitespace-nowrap text-xs text-muted sm:table-cell">{{ $link->created_at?->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-muted">No links yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($links->hasPages())
            <div class="border-t border-slate-500/10 px-4 py-3">{{ $links->links() }}</div>
        @endif
    </section>

    {{-- Folders, tags, bio --}}
    <div class="grid gap-3 lg:grid-cols-3">
        <section class="glass-card">
            <div class="flex items-center justify-between">
                <h2 class="section-title">Folders</h2>
                <span class="text-xs text-muted">{{ number_format($user->folders_count) }}</span>
            </div>
            <div class="mt-3 space-y-1.5">
                @forelse ($folders as $folder)
                    <div class="glass-inset flex items-center justify-between px-3 py-2 text-sm">
                        <span class="truncate text-ink">{{ $folder->name }} @if ($folder->is_default)<span class="text-xs text-muted">(default)</span>@endif</span>
                        <span class="shrink-0 text-xs tabular-nums text-muted">{{ number_format($folder->links_count) }}</span>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-muted">No folders</p>
                @endforelse
            </div>
        </section>
        <section class="glass-card">
            <div class="flex items-center justify-between">
                <h2 class="section-title">Tags</h2>
                <span class="text-xs text-muted">{{ number_format($user->tags_count) }}</span>
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5">
                @forelse ($tags as $tag)
                    <span class="chip-gray">#{{ $tag->name }} <span class="opacity-60">{{ $tag->links_count }}</span></span>
                @empty
                    <p class="w-full py-4 text-center text-sm text-muted">No tags</p>
                @endforelse
            </div>
        </section>
        <section class="glass-card">
            <h2 class="section-title">Bio pages</h2>
            <div class="mt-3 space-y-1.5">
                @forelse ($bioPages as $page)
                    <a href="{{ route('admin.bio.show', $page) }}" class="glass-inset flex items-center justify-between px-3 py-2 text-sm hover:opacity-80">
                        <span class="truncate font-mono text-ink">/b/{{ $page->slug }}</span>
                        <span class="{{ $page->is_published ? 'chip-green' : 'chip-gray' }}">{{ $page->is_published ? 'Live' : 'Draft' }}</span>
                    </a>
                @empty
                    <p class="py-4 text-center text-sm text-muted">No bio pages</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
