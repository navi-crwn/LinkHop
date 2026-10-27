<x-app-layout>
    <x-slot name="pageTitle">LinkHop - IP bans</x-slot>
    <x-page-header title="IP bans" subtitle="Blocked addresses can't sign up, sign in or use the public forms." />

    <x-flash />

    <div class="grid items-start gap-3 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.ip-bans.store') }}" class="glass-card space-y-3 lg:sticky lg:top-24">
            @csrf
            <h2 class="section-title">Ban an address</h2>
            <div>
                <label for="fadmini-1" class="label">IP address</label>
                <input id="fadmini-1" type="text" name="ip_address" value="{{ old('ip_address') }}" class="input font-mono" placeholder="217.15.164.195" required>
            </div>
            <div>
                <label for="fadmini-3" class="label">Reason</label>
                <input id="fadmini-3" type="text" name="reason" value="{{ old('reason') }}" class="input" placeholder="Spam or abuse">
            </div>
            <div>
                <label for="fadmini-2" class="label">Expires (optional)</label>
                <input id="fadmini-2" type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="input">
            </div>
            <button class="btn-danger w-full">Add IP ban</button>
        </form>

        <section class="glass overflow-hidden rounded-2xl lg:col-span-2">
            <div class="flex items-center justify-between px-4 pb-2 pt-4">
                <h2 class="section-title">Active bans</h2>
                <span class="text-xs text-muted">{{ number_format($bans->total()) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Reason</th>
                            <th>Expires</th>
                            <th class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bans as $ban)
                            <tr>
                                <td class="whitespace-nowrap font-mono text-sm font-semibold text-ink">{{ $ban->ip_address }}</td>
                                <td class="max-w-xs truncate text-sm text-ink">{{ $ban->reason ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">
                                    @if ($ban->expires_at)
                                        <span class="text-ink">{{ $ban->expires_at->format('d M Y H:i') }}</span>
                                    @else
                                        <span class="chip-gray">Permanent</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.ip-bans.destroy', $ban) }}" data-confirm="Remove the ban on {{ $ban->ip_address }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-ghost btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 text-center text-sm text-muted">No IP bans yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($bans->hasPages())
                <div class="border-t border-slate-500/10 px-4 py-3">{{ $bans->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
