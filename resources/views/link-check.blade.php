<x-marketing-layout title="Check a link - LinkHop">
    @php
        $card = 'rounded-3xl border border-white/10 bg-white/4 backdrop-blur-xl';
        $host = parse_url(config('app.url'), PHP_URL_HOST);
    @endphp
    <section class="mx-auto max-w-2xl px-4 pb-10 pt-14 sm:pt-20">
        <h1 class="text-center text-3xl font-semibold tracking-tight sm:text-4xl">Where does this link go?</h1>
        <p class="mx-auto mt-3 max-w-md text-center text-white/60">Paste a {{ $host }} link to see its destination before you open it. Nothing is clicked or tracked.</p>

        <form method="GET" action="{{ route('link.check') }}" class="{{ $card }} mt-8 flex flex-col gap-2 p-2 sm:flex-row">
            <label for="url" class="sr-only">Short link</label>
            <input id="url" name="url" value="{{ $input }}" required autofocus placeholder="{{ $host }}/abc123"
                class="min-w-0 flex-1 rounded-2xl border-0 bg-white/6 px-4 py-3.5 text-[15px] text-white placeholder-white/35 focus:outline-hidden focus:ring-2 focus:ring-indigo-400/50">
            <button class="rounded-2xl bg-white px-6 py-3.5 font-semibold text-slate-900 transition hover:bg-white/90 active:scale-[0.98]">Check</button>
        </form>

        @if ($result)
            <div class="{{ $card }} mt-4 p-5 sm:p-6">
                @switch($result['type'])
                    @case('foreign')
                        <p class="font-medium">That isn't a {{ $host }} link.</p>
                        <p class="mt-1 text-sm text-white/60">This tool only checks links on {{ $host }} (you entered {{ $result['host'] }}).</p>
                        @break

                    @case('missing')
                        <p class="font-medium">No link found.</p>
                        <p class="mt-1 text-sm text-white/60">{{ $host }}/{{ $result['slug'] }} doesn't exist, was removed, or has expired.</p>
                        @break

                    @case('bio')
                        <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Link in Bio page</p>
                        <p class="mt-2 text-lg font-semibold">{{ $result['page']->title }}</p>
                        <p class="text-sm text-white/60">{{ $host }}/b/{{ $result['page']->slug }} · {{ $result['page']->links_count }} links</p>
                        <a href="{{ url('/b/'.$result['page']->slug) }}" rel="noopener" class="mt-4 inline-block rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900">Open page</a>
                        @break

                    @case('link')
                        @php $link = $result['link']; @endphp
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-mono text-sm text-white/70">{{ $host }}/{{ $link->slug }}</p>
                            @if ($result['state'] === 'active')
                                <span class="rounded-full bg-emerald-400/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">Active</span>
                            @else
                                <span class="rounded-full bg-white/10 px-2 py-0.5 text-[11px] font-semibold text-white/70">{{ ucfirst($result['state']) }}</span>
                            @endif
                        </div>

                        @if ($result['protected'])
                            <p class="mt-4 font-medium">This link is password protected.</p>
                            <p class="mt-1 text-sm text-white/60">Its destination is only shown after entering the password.</p>
                        @elseif (! empty($result['hidden']))
                            <p class="mt-4 font-medium">This link no longer works.</p>
                            <p class="mt-1 text-sm text-white/70">Its destination is not shown because the link is {{ $result['state'] }}.</p>
                        @else
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-white/70">Destination</p>
                            <p class="mt-1 break-all text-[15px] text-white" x-data>
                                {{ $link->target_url }}
                            </p>
                            <p class="mt-1 text-sm text-white/70">Domain: <span class="font-medium text-white/80">{{ $result['host'] }}</span></p>

                            @if ($result['blocked'])
                                <div class="mt-4 rounded-2xl bg-rose-500/15 p-3 text-sm text-rose-100 ring-1 ring-rose-400/30">
                                    <p class="font-semibold">Unsafe destination</p>
                                    <p class="mt-0.5 text-rose-100/80">Flagged as: {{ implode(', ', $result['blocked']) }}. Don't open it.</p>
                                </div>
                            @elseif ($result['signals'])
                                <div class="mt-4 rounded-2xl bg-amber-400/10 p-3 text-sm text-amber-100 ring-1 ring-amber-300/25">
                                    <p class="font-semibold">Be careful</p>
                                    <p class="mt-0.5 text-amber-100/80">Notes: {{ str_replace('_', ' ', implode(', ', $result['signals'])) }}.</p>
                                </div>
                            @else
                                <p class="mt-4 text-sm text-emerald-300">No known issues found for this destination.</p>
                            @endif

                            <div class="mt-5 flex flex-wrap gap-2" x-data="{ copied: false }">
                                <button type="button" class="rounded-xl border border-white/15 px-4 py-2 text-sm text-white/85 hover:bg-white/10"
                                    @click="navigator.clipboard.writeText(@js($link->target_url)); copied = true; setTimeout(() => copied = false, 2000)">
                                    <span x-text="copied ? 'Copied' : 'Copy destination'">Copy destination</span>
                                </button>
                                @if ($result['state'] === 'active' && ! $result['blocked'])
                                    <a href="{{ $link->target_url }}" rel="noopener noreferrer nofollow" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900">Open destination</a>
                                @endif
                            </div>
                        @endif
                        <p class="mt-5 border-t border-white/10 pt-4 text-xs text-white/70">
                            Created {{ $link->created_at?->diffForHumans() }}{{ $link->user_id ? '' : ' by a guest' }}.
                            Something wrong? <a href="{{ route('report.create', ['slug' => $link->slug]) }}" class="underline hover:text-white">Report this link</a>.
                        </p>
                        @break
                @endswitch
            </div>
        @endif
    </section>
</x-marketing-layout>
