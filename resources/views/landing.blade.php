<x-marketing-layout title="LinkHop - Modern URL Shortener" :themed="true">
    @php
        $idleDays = config('shortener.guest_idle_days', 14);
        $maxDays = config('shortener.guest_max_days', 30);
        $card = 'rounded-3xl border border-slate-200/80 bg-white/80 shadow-xs shadow-slate-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-white/5 dark:shadow-none';
        $btnPrimary = 'rounded-xl bg-linear-to-r from-indigo-500 via-sky-500 to-fuchsia-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/20 transition hover:brightness-110 active:scale-[0.98]';
        $btnSecondary = 'rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-900/5 dark:border-white/15 dark:text-white/80 dark:hover:bg-white/10';
    @endphp

    {{-- Hero: the free guest shortener on the left, a small copy of the hop scene on the right --}}
    <section class="mx-auto grid max-w-6xl items-center gap-6 px-4 pb-4 pt-10 sm:pt-14 lg:grid-cols-[1.05fr_1fr]">
        <div class="text-center lg:text-left">
        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.3em] text-slate-500 dark:text-white/50">LinkHop · by hel.ink</p>
        <h1 class="mt-4 text-5xl font-extrabold leading-[1.02] tracking-[-0.045em] sm:text-7xl lg:text-[76px]">
            hel.ink/<span class="hop-serif bg-linear-to-r from-sky-500 via-indigo-500 to-fuchsia-500 bg-clip-text pr-2 text-transparent dark:from-sky-300 dark:via-indigo-300 dark:to-fuchsia-300">anything</span>
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-base text-slate-600 sm:text-lg lg:mx-0 dark:text-white/65">
            Turn a long, ugly link into one that's short, trackable and safe to share. Free, open source, no tracking ads.
        </p>

        <div class="{{ $card }} mx-auto mt-8 max-w-2xl p-2 text-left lg:mx-0 shadow-xl shadow-indigo-500/10 ring-1 ring-indigo-500/10 dark:shadow-2xl dark:shadow-black/30 dark:ring-0">
            <form
                x-data="Object.assign({ custom: {{ old('slug') ? 'true' : 'false' }} }, turnstileForm(@js(config('services.turnstile.site_key'))))"
                x-on:submit="handleSubmit($event)"
                method="POST"
                action="{{ route('shorten.store') }}"
            >
                @csrf
                {{-- Row 1: link + Shorten. Row 2: custom slug and sign-up hint on the left; the security check
                     (normal size) right under Shorten, shown only when Cloudflare asks for a click. --}}
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label for="target_url" class="sr-only">Long URL</label>
                    <input id="target_url" type="url" name="target_url" value="{{ old('target_url') }}" required
                        placeholder="Paste a long link…"
                        class="min-w-0 flex-1 rounded-2xl border-0 bg-slate-100 px-4 py-3.5 text-[15px] text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-indigo-400/50 dark:bg-white/6 dark:text-white dark:placeholder-white/35 dark:focus:bg-white/9">
                    <button type="submit"
                        class="rounded-2xl bg-linear-to-r from-indigo-500 via-sky-500 to-fuchsia-500 px-6 py-3.5 font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:brightness-110 active:scale-[0.98] disabled:opacity-50">
                        <span x-show="!waiting">Shorten</span><span x-cloak x-show="waiting">Verifying…</span>
                    </button>
                </div>
                <div class="flex flex-col-reverse px-2 pb-1 pt-2 sm:flex-row sm:items-start sm:gap-4">
                    <div class="min-w-0 flex-1">
                        <div x-show="!custom" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs"
                            x-bind:class="challenge && 'sm:flex-col sm:items-start sm:pt-2 sm:gap-y-3'">
                            <button type="button" class="text-slate-500 hover:text-slate-900 dark:text-white/55 dark:hover:text-white" @click="custom = true">+ Custom slug</button>
                            <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-300">Join LinkHop for more features →</a>
                        </div>
                        <div x-show="custom" x-cloak class="flex items-center gap-2">
                            <span class="text-sm text-slate-400 dark:text-white/45">{{ public_host() }}/l/</span>
                            <input id="slug" type="text" name="slug" value="{{ old('slug') }}" placeholder="my-link" pattern="[A-Za-z0-9\-]+"
                                class="min-w-0 flex-1 rounded-xl border-0 bg-slate-100 px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-400/50 dark:bg-white/6 dark:text-white dark:placeholder-white/30">
                        </div>
                    </div>
                    {{-- Kept rendered but collapsed (Turnstile needs a live container). --}}
                    <x-turnstile class="flex-none self-center sm:self-auto"
                        x-bind:class="challenge || status === 'error' ? 'mb-2 sm:mb-0' : 'h-0 w-0 overflow-hidden'" />
                </div>
                @if (session('link_error') || $errors->any())
                    <div class="m-2 rounded-xl bg-rose-500/10 px-3 py-2 text-sm text-rose-700 dark:bg-rose-500/15 dark:text-rose-100">
                        {{ session('link_error') }}
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
            </form>

            @if ($shortened = session('shortened_link'))
                <div class="m-2 mt-1 rounded-2xl bg-emerald-500/10 p-3 ring-1 ring-emerald-500/30 dark:bg-emerald-400/10 dark:ring-emerald-400/30" x-data="{ copied: false }">
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-mono text-[15px] font-semibold text-slate-900 dark:text-white">{{ $shortened->short_url }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-white/55">→ {{ $shortened->target_url }}</p>
                        </div>
                        <button type="button" class="rounded-xl bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-900"
                            x-on:click="navigator.clipboard.writeText(@js($shortened->short_url)); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-white/60">
                        Guest links are deleted after {{ $idleDays }} days in a row without a click, and after {{ $maxDays }} days at most.
                        <a href="{{ route('register') }}" class="font-semibold text-indigo-600 underline-offset-2 hover:underline dark:text-white">Create a free account</a> to keep links permanently.
                    </p>
                </div>
            @else
                <p class="px-3 pb-2 text-[11px] text-slate-500 dark:text-white/60">
                    No account needed. Guest links are deleted after {{ $idleDays }} days without a click, and after {{ $maxDays }} days at most.
                </p>
            @endif
        </div>
        </div>
        {{-- the miniature: plain hops only, desktop only (phones get the full scene below) --}}
        <div class="relative hidden h-[460px] select-none lg:block" data-hop-scene data-mini aria-hidden="true">
            <div class="absolute inset-0" data-hop-canvas></div>
            <p class="absolute inset-x-0 top-2 text-center text-xs font-semibold text-slate-500 dark:text-white/55" data-hop-story></p>
        </div>
    </section>

    {{-- The hop: a holographic sea; islands rise, bunnies hop through hel.ink to where a link goes (resources/js/hop-hero.js) --}}
    <section class="mx-auto mt-4 max-w-6xl select-none px-2 pb-8 sm:px-4" aria-label="How a short link works" data-hop-scene>
        <div class="flex flex-col items-center gap-2 px-2">
            <div class="flex flex-wrap justify-center gap-2" role="group" aria-label="Play a scenario">
                @foreach (['hop' => 'Hop to a link', 'share' => 'Share a link', 'pass-ok' => 'Password: right', 'pass-fail' => 'Password: wrong'] as $key => $label)
                    <button type="button" data-hop-play="{{ $key }}" aria-pressed="false" class="hop-pill rounded-full border border-slate-200 bg-white/80 px-3.5 py-1.5 text-xs font-semibold text-slate-600 backdrop-blur transition hover:border-indigo-300 hover:text-indigo-700 sm:text-[13px] dark:border-white/10 dark:bg-slate-900/60 dark:text-white/70 dark:hover:text-white">{{ $label }}</button>
                @endforeach
            </div>
            <p class="min-h-5 text-center text-[13px] font-semibold text-slate-500 dark:text-white/60" data-hop-story aria-live="polite"></p>
        </div>
        <div class="relative h-[360px] sm:h-[420px]" data-hop-canvas>
            <img data-hop-fallback src="{{ route('brand.asset', 'linkhop-mark-gradient.svg') }}" alt="" class="absolute inset-0 m-auto h-32 w-auto opacity-80">
        </div>
    </section>
    @vite('resources/js/hop-hero.js')

    {{-- Features --}}
    <section id="features" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-10">
        @php
            $features = [
                ['icon' => 'M4 20V10m6 10V4m6 16v-7', 'title' => 'Analytics', 'body' => 'Clicks over time, countries, cities, devices, referrers and networks.'],
                ['icon' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2z', 'title' => 'QR codes', 'body' => 'Styled QR codes with your logo, as PNG, SVG or JPG.'],
                ['icon' => 'M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z', 'title' => 'Protected links', 'body' => 'Passwords, expiry dates, redirect types and automatic abuse scanning.'],
                ['icon' => 'M4 7h16M4 12h10M4 17h7', 'title' => 'Organised', 'body' => 'Folders, tags, bulk actions and a REST API for ShareX and tools.'],
            ];
        @endphp
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($features as $f)
                <article class="{{ $card }} p-4 sm:p-5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-linear-to-br from-indigo-500/15 to-fuchsia-500/15 dark:from-indigo-400/20 dark:to-fuchsia-400/15">
                        <svg class="h-[18px] w-[18px] text-indigo-600 dark:text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $f['icon'] }}"/></svg>
                    </div>
                    <h3 class="mt-4 font-semibold">{{ $f['title'] }}</h3>
                    <p class="mt-1 text-[13px] leading-relaxed text-slate-600 sm:text-sm dark:text-white/60">{{ $f['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Link in Bio --}}
    <section id="bio" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-10">
        <div class="{{ $card }} grid items-center gap-8 overflow-hidden p-6 sm:p-10 md:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-200/80">Link in Bio</p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">All your links on one page.</h2>
                <p class="mt-3 text-slate-600 dark:text-white/60">20+ themes, social icons, music and video embeds, countdowns, FAQ blocks and per-link click stats. Drag to reorder, publish instantly.</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('register') }}" class="{{ $btnPrimary }}">Create yours</a>
                    <a href="/b/hel" class="{{ $btnSecondary }}">See an example</a>
                </div>
            </div>
            <div class="mx-auto w-full max-w-[260px]">
                <div class="rounded-4xl border border-white/40 bg-linear-to-b from-indigo-500 via-sky-500 to-fuchsia-500 p-5 shadow-2xl shadow-indigo-500/25 dark:border-white/15 dark:from-indigo-500/40 dark:via-sky-500/25 dark:to-fuchsia-500/30 dark:shadow-black/30">
                    <div class="mx-auto h-14 w-14 rounded-full bg-white/20 ring-2 ring-white/30"></div>
                    <div class="mx-auto mt-3 h-2.5 w-24 rounded-full bg-white/60"></div>
                    <div class="mx-auto mt-2 h-2 w-32 rounded-full bg-white/25"></div>
                    <div class="mt-5 space-y-2.5">
                        @foreach (['Latest video', 'My shop', 'Podcast', 'Contact'] as $l)
                            <div class="rounded-xl border border-white/15 bg-white/10 px-3 py-2.5 text-center text-xs font-medium text-white/90 backdrop-blur-sm">{{ $l }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Call to action + family --}}
    <section class="mx-auto grid max-w-6xl gap-3 px-4 pb-6 md:grid-cols-4">
        <div class="{{ $card }} p-6 md:col-span-2">
            <h2 class="text-xl font-semibold">Open source, free forever</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-white/60">Built with Laravel, Tailwind and Alpine.js. Use hel.ink or host it yourself.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('register') }}" class="{{ $btnPrimary }}">Create free account</a>
                <a href="https://github.com/navi-crwn/hel.ink" target="_blank" rel="noopener" class="{{ $btnSecondary }}">GitHub</a>
            </div>
        </div>
        <a href="https://p.hel.ink" target="_blank" rel="noopener" class="{{ $card }} group block p-6 transition hover:bg-white dark:hover:bg-white/8">
            <p class="text-xs font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-200/80">LinkHop family</p>
            <h2 class="mt-2 text-xl font-semibold">PixelHop</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-white/60">Free image hosting with compress, resize, convert, OCR and background removal.</p>
            <p class="mt-4 text-sm font-medium text-cyan-700 group-hover:underline dark:text-cyan-200">p.hel.ink →</p>
        </a>
        <a href="https://note.hel.ink" target="_blank" rel="noopener" class="{{ $card }} group block p-6 transition hover:bg-white dark:hover:bg-white/8">
            <p class="text-xs font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-200/80">LinkHop family</p>
            <h2 class="mt-2 text-xl font-semibold">NoteHop</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-white/60">Encrypted notes you share with a link: delete after reading, write together, polls and checklists.</p>
            <p class="mt-4 text-sm font-medium text-cyan-700 group-hover:underline dark:text-cyan-200">note.hel.ink →</p>
        </a>
    </section>
</x-marketing-layout>
