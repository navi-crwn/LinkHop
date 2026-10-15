<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Domain blacklist</x-slot>
    @php
        $categoryChip = ['phishing' => 'chip-red', 'malware' => 'chip-red', 'scam' => 'chip-amber', 'porn' => 'chip-amber', 'spam' => 'chip-gray'];
    @endphp
    <x-page-header title="Domain blacklist" subtitle="Links to these domains are refused and flagged by the abuse scan." />

    <x-flash />

    <div class="grid items-start gap-3 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.domain-blacklist.store') }}" class="glass-card space-y-3 lg:sticky lg:top-24">
            @csrf
            <h2 class="section-title">Block domains</h2>
            <div>
                <label for="domain" class="label">Domains, one per line</label>
                <textarea name="domain" id="domain" rows="3" required class="input font-mono text-sm" placeholder="example.com&#10;*.spam.com">{{ old('domain') }}</textarea>
                <p class="mt-1 text-[11px] text-muted">Without http:// or www.</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="match_type" class="label">Match</label>
                    <select name="match_type" id="match_type" required class="input">
                        <option value="exact">Exact</option>
                        <option value="wildcard" @selected(old('match_type') === 'wildcard')>+ subdomains</option>
                    </select>
                </div>
                <div>
                    <label for="category" class="label">Category</label>
                    <select name="category" id="category" class="input">
                        <option value="">None</option>
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label for="notes" class="label">Notes (optional)</label>
                <textarea name="notes" id="notes" rows="2" class="input" placeholder="Why is this domain blocked?">{{ old('notes') }}</textarea>
            </div>
            <button type="submit" class="btn-danger w-full">Add to blacklist</button>
        </form>

        <section class="glass overflow-hidden rounded-2xl lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 pb-2 pt-4">
                <h2 class="section-title">Blocked domains <span class="font-normal text-muted">· {{ number_format($domains->total()) }}</span></h2>
                <form method="GET" class="w-full sm:w-56">
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search domain" aria-label="Search domains" class="input py-1.5! text-sm">
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Match</th>
                            <th>Category</th>
                            <th class="hidden md:table-cell">Notes</th>
                            <th class="hidden sm:table-cell">Added</th>
                            <th class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($domains as $domain)
                            <tr>
                                <td class="whitespace-nowrap font-mono text-sm font-semibold text-ink">{{ $domain->domain }}</td>
                                <td><span class="{{ $domain->match_type === 'wildcard' ? 'chip-blue' : 'chip-gray' }}">{{ $domain->match_type === 'wildcard' ? '+ subdomains' : 'Exact' }}</span></td>
                                <td>
                                    @if ($domain->category)
                                        <span class="{{ $categoryChip[$domain->category] ?? 'chip-gray' }}">{{ $categories[$domain->category] ?? ucfirst($domain->category) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="hidden max-w-xs md:table-cell"><p class="truncate text-xs text-muted" title="{{ $domain->notes }}">{{ $domain->notes ?: '—' }}</p></td>
                                <td class="hidden whitespace-nowrap text-xs text-muted sm:table-cell">{{ $domain->created_at?->format('d M Y') }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.domain-blacklist.destroy', $domain) }}" data-confirm="Unblock {{ $domain->domain }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-ghost btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-sm text-muted">{{ $search ? 'No blocked domain matches that search.' : 'No domains blocked yet.' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($domains->hasPages())
                <div class="border-t border-slate-500/10 px-4 py-3">{{ $domains->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
