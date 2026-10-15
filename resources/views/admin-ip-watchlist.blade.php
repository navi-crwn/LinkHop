<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - IP watchlist</x-slot>
    <x-page-header title="IP watchlist" subtitle="Addresses that are logged and flagged but not blocked." />

    <x-flash />

    <div class="grid items-start gap-3 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.ip-watchlist.store') }}" class="glass-card space-y-3 lg:sticky lg:top-24">
            @csrf
            <h2 class="section-title">Watch addresses</h2>
            <div>
                <label for="ip_address" class="label">IP addresses, one per line</label>
                <textarea name="ip_address" id="ip_address" rows="3" required class="input font-mono text-sm" placeholder="192.168.1.100&#10;10.0.0.50">{{ old('ip_address') }}</textarea>
            </div>
            <div>
                <label for="reason" class="label">Reason</label>
                <input type="text" name="reason" id="reason" value="{{ old('reason') }}" class="input" placeholder="Why is this IP suspicious?">
            </div>
            <div>
                <label for="notes" class="label">Notes (optional)</label>
                <textarea name="notes" id="notes" rows="2" class="input">{{ old('notes') }}</textarea>
            </div>
            <button type="submit" class="btn-primary w-full">Add to watchlist</button>
        </form>

        <section class="glass overflow-hidden rounded-2xl lg:col-span-2">
            <div class="flex items-center justify-between px-4 pb-2 pt-4">
                <h2 class="section-title">Watched addresses</h2>
                <span class="text-xs text-muted">{{ number_format($watchlist->total()) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th class="hidden md:table-cell">User</th>
                            <th>Reason</th>
                            <th class="text-right">Attempts</th>
                            <th class="hidden sm:table-cell">Last seen</th>
                            <th class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($watchlist as $item)
                            <tr>
                                <td class="whitespace-nowrap font-mono text-sm font-semibold text-ink">{{ $item->ip_address }}</td>
                                <td class="hidden max-w-48 md:table-cell">
                                    @if ($item->user)
                                        <a href="{{ route('admin.users.inspect', $item->user) }}" class="block truncate text-sm text-ink hover:underline">{{ $item->user->name }}</a>
                                        <p class="truncate text-xs text-muted">{{ $item->user->email }}</p>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="max-w-xs">
                                    <p class="truncate text-sm text-ink">{{ $item->reason ?: '—' }}</p>
                                    @if ($item->notes)
                                        <p class="truncate text-xs text-muted" title="{{ $item->notes }}">{{ $item->notes }}</p>
                                    @endif
                                </td>
                                <td class="text-right"><span class="{{ $item->attempt_count > 0 ? 'chip-amber' : 'chip-gray' }} tabular-nums">{{ number_format($item->attempt_count) }}</span></td>
                                <td class="hidden whitespace-nowrap text-xs text-muted sm:table-cell">{{ $item->last_attempt_at?->diffForHumans() ?? '—' }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.ip-watchlist.destroy', $item) }}" data-confirm="Stop watching {{ $item->ip_address }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-ghost btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-sm text-muted">No addresses on the watchlist.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($watchlist->hasPages())
                <div class="border-t border-slate-500/10 px-4 py-3">{{ $watchlist->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
