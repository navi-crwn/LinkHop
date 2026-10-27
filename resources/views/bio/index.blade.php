<x-app-layout>
    <x-slot name="pageTitle">LinkHop - Link in bio</x-slot>
    @php $max = \App\Http\Controllers\BioPageController::MAX_PAGES; @endphp
    <x-page-header title="Link in bio" subtitle="{{ $bioPages->count() }} of {{ $max }} pages used" />

    <x-flash />

    @if ($bioPages->isEmpty())
        <div class="glass-card py-12 text-center">
            <p class="text-sm font-semibold text-ink">No bio pages yet</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Share all your important links from one page.</p>
            <a href="{{ route('bio.create') }}" class="btn-primary btn-sm mt-4">Create your first bio page</a>
        </div>
    @else
        @php
            $gradients = ['from-indigo-500 to-purple-600', 'from-emerald-500 to-cyan-600', 'from-rose-500 to-fuchsia-600', 'from-amber-500 to-red-500', 'from-sky-500 to-indigo-600'];
        @endphp
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($bioPages as $bioPage)
                <section class="glass overflow-hidden rounded-2xl">
                    <div class="h-16 bg-linear-to-br {{ $gradients[$loop->index % count($gradients)] }}"></div>
                    <div class="-mt-8 space-y-3 px-4 pb-4">
                        <div class="flex items-end gap-3">
                            @if ($bioPage->avatar_url)
                                <img src="{{ Storage::disk('public')->url($bioPage->avatar_url) }}" alt="" class="h-14 w-14 shrink-0 rounded-2xl border-2 border-white object-cover shadow-sm dark:border-slate-900">
                            @else
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border-2 border-white bg-indigo-500 text-xl font-semibold text-white shadow-sm dark:border-slate-900">{{ strtoupper(mb_substr($bioPage->title, 0, 1)) }}</span>
                            @endif
                            <div class="min-w-0 flex-1 pb-0.5">
                                <div class="flex items-center gap-1.5">
                                    <h2 class="truncate text-sm font-semibold text-ink">{{ $bioPage->title }}</h2>
                                    @unless ($bioPage->is_published)
                                        <span class="chip-amber">Draft</span>
                                    @endunless
                                </div>
                                <a href="{{ url('/b/'.$bioPage->slug) }}" target="_blank" rel="noopener" class="block truncate text-xs text-muted hover:underline">hel.ink/b/{{ $bioPage->slug }}</a>
                            </div>
                        </div>

                        <dl class="grid grid-cols-3 gap-2 text-center">
                            <div class="glass-inset rounded-xl py-1.5"><dt class="text-[11px] text-muted">Views</dt><dd class="text-sm font-semibold tabular-nums text-ink">{{ number_format($bioPage->view_count) }}</dd></div>
                            <div class="glass-inset rounded-xl py-1.5"><dt class="text-[11px] text-muted">Clicks</dt><dd class="text-sm font-semibold tabular-nums text-ink">{{ number_format($bioPage->getTotalClicks()) }}</dd></div>
                            <div class="glass-inset rounded-xl py-1.5"><dt class="text-[11px] text-muted">Links</dt><dd class="text-sm font-semibold tabular-nums text-ink">{{ number_format($bioPage->links_count) }}</dd></div>
                        </dl>

                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('bio.edit', $bioPage) }}" class="btn-primary btn-sm flex-1 justify-center">Edit</a>
                            <a href="{{ route('bio.analytics', $bioPage) }}" class="btn-soft btn-sm flex-1 justify-center">Analytics</a>
                            <form action="{{ route('bio.duplicate', $bioPage) }}" method="POST">
                                @csrf
                                <button type="submit" class="icon-btn h-8! w-8!" title="Duplicate" aria-label="Duplicate {{ $bioPage->title }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </form>
                            <form action="{{ route('bio.destroy', $bioPage) }}" method="POST" data-confirm="Delete {{ $bioPage->title }}? This can't be undone.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-btn h-8! w-8! hover:text-rose-500!" title="Delete" aria-label="Delete {{ $bioPage->title }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-app-layout>
