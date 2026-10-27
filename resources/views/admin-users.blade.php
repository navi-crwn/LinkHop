<x-app-layout>
    <x-slot name="pageTitle">LinkHop - Users</x-slot>
    @php
        $tabs = ['' => ['All', $counts['all']], 'active' => ['Active', $counts['active']], 'suspended' => ['Suspended', $counts['suspended']]];
        $query = fn (array $extra) => route('admin.users.index', array_filter(array_merge(['q' => $search, 'status' => $status, 'sort' => $sort], $extra), fn ($v) => $v !== '' && $v !== null));
    @endphp

    <x-page-header title="Users" subtitle="{{ number_format($counts['all']) }} accounts · {{ number_format($counts['new']) }} new this week">
        @if ($viewerIsSuperAdmin)
            <x-slot name="actions">
                <button type="button" class="btn-primary btn-sm" x-data @click="$dispatch('open-modal', 'create-user')">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add user
                </button>
            </x-slot>
        @endif
    </x-page-header>

    <x-flash />

    <div class="glass-card flex flex-col gap-3 p-3! md:flex-row md:items-center">
        <nav class="flex shrink-0 gap-1" aria-label="Filter by status">
            @foreach ($tabs as $value => [$label, $count])
                <a href="{{ $query(['status' => $value, 'page' => null]) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $status === $value ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-muted hover:text-ink' }}">
                    {{ $label }} <span class="ml-0.5 text-xs opacity-70">{{ number_format($count) }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" class="flex flex-1 gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name or email" aria-label="Search users" class="input py-2! pl-9">
            </div>
            <select name="sort" aria-label="Sort" class="input w-auto! py-2!" data-autosubmit>
                <option value="">Newest</option>
                <option value="login" @selected($sort === 'login')>Last login</option>
                <option value="links" @selected($sort === 'links')>Most links</option>
                <option value="name" @selected($sort === 'name')>Name A–Z</option>
            </select>
        </form>
    </div>

    <section class="glass overflow-hidden rounded-2xl">
        <div class="overflow-x-auto">
            <table class="table-glass">
                <thead>
                    <tr>
                        <th>User</th>
                        <th class="hidden text-right sm:table-cell">Links</th>
                        <th class="hidden text-right sm:table-cell">Clicks</th>
                        <th class="hidden sm:table-cell">Status</th>
                        <th class="hidden lg:table-cell">Last login</th>
                        <th class="hidden xl:table-cell">Joined</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php $isSuperAdminRow = $user->isSuperAdmin(); @endphp
                        <tr>
                            <td class="max-w-52 sm:max-w-[18rem]">
                                <a href="{{ route('admin.users.inspect', $user) }}" class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-xs font-semibold text-indigo-600 dark:text-indigo-300">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <span class="truncate text-sm font-semibold text-ink">{{ $user->name }}</span>
                                            @if ($isSuperAdminRow)
                                                <span class="chip-amber">Admin</span>
                                            @endif
                                            @if ($user->status !== 'active')
                                                <span class="chip-red sm:hidden">Suspended</span>
                                            @endif
                                        </span>
                                        <span class="block truncate text-xs text-muted">{{ $user->email }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="hidden text-right tabular-nums text-ink sm:table-cell">{{ number_format($user->links_count) }}</td>
                            <td class="hidden text-right tabular-nums text-ink sm:table-cell">{{ number_format((int) $user->links_sum_clicks) }}</td>
                            <td class="hidden sm:table-cell"><span class="{{ $user->status === 'active' ? 'chip-green' : 'chip-red' }}">{{ ucfirst($user->status) }}</span></td>
                            <td class="hidden whitespace-nowrap lg:table-cell">
                                <p class="text-xs text-ink">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</p>
                                @if ($user->last_login_ip)
                                    <p class="font-mono text-[11px] text-muted">{{ $user->last_login_ip }}{{ $user->last_login_country ? ' · '.$user->last_login_country : '' }}</p>
                                @endif
                            </td>
                            <td class="hidden whitespace-nowrap text-xs text-muted xl:table-cell">{{ $user->created_at?->format('d M Y') }}</td>
                            <td class="whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-0.5">
                                    <a href="{{ route('admin.users.inspect', $user) }}" class="icon-btn h-8! w-8!" title="Inspect" aria-label="Inspect {{ $user->name }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    @if ($viewerIsSuperAdmin && ! $isSuperAdminRow)
                                        <form method="POST" action="{{ route('admin.users.status', $user) }}" @if ($user->status === 'active') data-confirm="Suspend {{ $user->email }}?" @endif>
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $user->status === 'active' ? 'suspended' : 'active' }}">
                                            <button class="icon-btn h-8! w-8!" title="{{ $user->status === 'active' ? 'Suspend' : 'Activate' }}" aria-label="{{ $user->status === 'active' ? 'Suspend' : 'Activate' }} {{ $user->name }}">
                                                @if ($user->status === 'active')
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                @else
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                @endif
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Delete {{ $user->email }} and all their links?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn h-8! w-8! hover:text-rose-500!" title="Delete" aria-label="Delete {{ $user->name }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-sm text-muted">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="border-t border-slate-500/10 px-4 py-3">{{ $users->links() }}</div>
        @endif
    </section>

    @if ($viewerIsSuperAdmin)
        <x-modal name="create-user" :show="$errors->hasAny(['name', 'email', 'password'])" maxWidth="md" focusable>
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4 p-5">
                @csrf
                <div>
                    <h2 class="section-title text-base">Add user</h2>
                    <p class="mt-0.5 text-xs text-muted">Creates a verified regular account without the sign-up flow.</p>
                </div>
                <div>
                    <label for="new-user-name" class="label">Name</label>
                    <input id="new-user-name" type="text" name="name" value="{{ old('name') }}" required autocomplete="off" class="input">
                    @error('name')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="new-user-email" class="label">Email</label>
                    <input id="new-user-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="off" class="input">
                    @error('email')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="new-user-password" class="label">Password</label>
                        <input id="new-user-password" type="password" name="password" required autocomplete="new-password" class="input">
                    </div>
                    <div>
                        <label for="new-user-password2" class="label">Confirm</label>
                        <input id="new-user-password2" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
                    </div>
                    @error('password')<p class="text-xs text-rose-500 sm:col-span-2">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" class="btn-ghost btn-sm" @click="$dispatch('close-modal', 'create-user')">Cancel</button>
                    <button type="submit" class="btn-primary btn-sm">Create user</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
