<x-marketing-layout title="HEL.ink - Modern URL Shortener">
    @php
        $idleDays = config('shortener.guest_idle_days', 14);
        $maxDays = config('shortener.guest_max_days', 30);
        $card = 'rounded-3xl border border-white/10 bg-white/[0.04] backdrop-blur-xl';
    @endphp

    {{-- Hero + shortener --}}
    <section class="mx-auto max-w-3xl px-4 pb-10 pt-14 text-center sm:pt-20">
        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/70">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Free · open source · no tracking ads
        </span>
        <h1 class="mt-5 text-4xl font-semibold leading-[1.1] tracking-tight sm:text-6xl">
            Short links,<br class="sm:hidden">
            <span class="bg-gradient-to-r from-indigo-300 via-sky-300 to-fuchsia-300 bg-clip-text text-transparent">one hop away.</span>
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base text-white/65 sm:text-lg">
            Shorten a link in seconds, or sign up for analytics, QR codes, folders and a Link in Bio page.
        </p>

        <div class="{{ $card }} mx-auto mt-8 p-2 text-left shadow-2xl shadow-indigo-950/40">
            <form
                x-data="Object.assign({ custom: {{ old('slug') ? 'true' : 'false' }} }, window.turnstileForm ? window.turnstileForm('{{ config('services.turnstile.site_key') }}', {{ $errors->has('turnstile') ? 'true' : 'false' }}) : {})"
                x-init="
                    if (!window.turnstileForm) {
                        let interval = setInterval(() => {
                            if (window.turnstileForm) {
                                Object.assign($data, window.turnstileForm('{{ config('services.turnstile.site_key') }}', {{ $errors->has('turnstile') ? 'true' : 'false' }}));
                                clearInterval(interval);
                            }
                        }, 50);
                    }
                "
                x-on:submit="handleSubmit && handleSubmit($event)"
                method="POST"
                action="{{ route('shorten.store') }}"
            >
                @csrf
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label for="target_url" class="sr-only">Long URL</label>
                    <input id="target_url" type="url" name="target_url" value="{{ old('target_url') }}" required
                        placeholder="Paste a long link…"
                        class="min-w-0 flex-1 rounded-2xl border-0 bg-white/[0.06] px-4 py-3.5 text-[15px] text-white placeholder-white/35 focus:bg-white/[0.09] focus:outline-none focus:ring-2 focus:ring-indigo-400/50">
                    <button type="submit" x-bind:disabled="typeof showCaptcha !== 'undefined' && showCaptcha && !token"
                        class="rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-500 px-6 py-3.5 font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:brightness-110 disabled:opacity-50">
                        Shorten
                    </button>
                </div>
                <div class="px-2 pb-1 pt-2">
                    <button type="button" class="text-xs text-white/55 hover:text-white" @click="custom = !custom" x-show="!custom">+ Custom slug</button>
                    <div x-show="custom" x-cloak class="flex items-center gap-2">
                        <span class="text-sm text-white/45">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/</span>
                        <input id="slug" type="text" name="slug" value="{{ old('slug') }}" placeholder="my-link" pattern="[A-Za-z0-9-]+"
                            class="min-w-0 flex-1 rounded-xl border-0 bg-white/[0.06] px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-indigo-400/50">
                    </div>
                </div>
                @if (config('services.turnstile.site_key'))
                    <div x-cloak x-show="showCaptcha" class="px-2 pt-2"><div x-ref="captcha"></div></div>
                    <input type="hidden" name="cf-turnstile-response" :value="token">
                @endif
                @if (session('link_error') || $errors->any())
                    <div class="m-2 rounded-xl bg-rose-500/15 px-3 py-2 text-sm text-rose-100">
                        {{ session('link_error') }}
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
            </form>

            @if ($shortened = session('shortened_link'))
                <div class="m-2 mt-1 rounded-2xl bg-emerald-400/10 p-3 ring-1 ring-emerald-400/30" x-data="{ copied: false }">
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-mono text-[15px] font-semibold text-white">{{ $shortened->short_url }}</p>
                            <p class="truncate text-xs text-white/55">→ {{ $shortened->target_url }}</p>
                        </div>
                        <button type="button" class="rounded-xl bg-white px-3.5 py-2 text-sm font-semibold text-slate-900"
                            x-on:click="navigator.clipboard.writeText(@js($shortened->short_url)); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-white/60">
                        Guest links are removed {{ $idleDays }} days after their last click, and after {{ $maxDays }} days at most.
                        <a href="{{ route('register') }}" class="font-semibold text-white underline-offset-2 hover:underline">Create a free account</a> to keep links permanently.
                    </p>
                </div>
            @else
                <p class="px-3 pb-2 text-[11px] text-white/40">
                    No account needed. Guest links expire after {{ $idleDays }} days without clicks ({{ $maxDays }} days max).
                </p>
            @endif
        </div>
    </section>

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
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10">
                        <svg class="h-[18px] w-[18px] text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $f['icon'] }}"/></svg>
                    </div>
                    <h3 class="mt-4 font-semibold">{{ $f['title'] }}</h3>
                    <p class="mt-1 text-[13px] leading-relaxed text-white/60 sm:text-sm">{{ $f['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Link in Bio --}}
    <section id="bio" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-10">
        <div class="{{ $card }} grid items-center gap-8 overflow-hidden p-6 sm:p-10 md:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-200/80">Link in Bio</p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">All your links on one page.</h2>
                <p class="mt-3 text-white/60">20+ themes, social icons, music and video embeds, countdowns, FAQ blocks and per-link click stats. Drag to reorder, publish instantly.</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('register') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 hover:bg-white/90">Create yours</a>
                    <a href="/b/hel" class="rounded-xl border border-white/15 px-4 py-2.5 text-sm text-white/80 hover:bg-white/10">See an example</a>
                </div>
            </div>
            <div class="mx-auto w-full max-w-[260px]">
                <div class="rounded-[2rem] border border-white/15 bg-gradient-to-b from-indigo-500/30 to-fuchsia-500/20 p-5 shadow-2xl">
                    <div class="mx-auto h-14 w-14 rounded-full bg-white/20 ring-2 ring-white/30"></div>
                    <div class="mx-auto mt-3 h-2.5 w-24 rounded-full bg-white/60"></div>
                    <div class="mx-auto mt-2 h-2 w-32 rounded-full bg-white/25"></div>
                    <div class="mt-5 space-y-2.5">
                        @foreach (['Latest video', 'My shop', 'Podcast', 'Contact'] as $l)
                            <div class="rounded-xl border border-white/15 bg-white/10 px-3 py-2.5 text-center text-xs font-medium text-white/90 backdrop-blur">{{ $l }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Call to action + family --}}
    <section class="mx-auto grid max-w-6xl gap-3 px-4 pb-6 md:grid-cols-3">
        <div class="{{ $card }} p-6 md:col-span-2">
            <h2 class="text-xl font-semibold">Open source, free forever</h2>
            <p class="mt-2 text-sm text-white/60">Built with Laravel, Tailwind and Alpine.js. Use hel.ink or host it yourself.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('register') }}" class="rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 px-4 py-2.5 text-sm font-semibold text-white">Create free account</a>
                <a href="https://github.com/navi-crwn/hel.ink" target="_blank" rel="noopener" class="rounded-xl border border-white/15 px-4 py-2.5 text-sm text-white/80 hover:bg-white/10">GitHub</a>
            </div>
        </div>
        <a href="https://p.hel.ink" target="_blank" rel="noopener" class="{{ $card }} group block p-6 transition hover:bg-white/[0.07]">
            <p class="text-xs font-semibold uppercase tracking-wider text-cyan-200/80">HEL.ink family</p>
            <h2 class="mt-2 text-xl font-semibold">PixelHop</h2>
            <p class="mt-2 text-sm text-white/60">Free image hosting with compress, resize, convert, OCR and background removal.</p>
            <p class="mt-4 text-sm font-medium text-cyan-200 group-hover:underline">p.hel.ink →</p>
        </a>
    </section>
</x-marketing-layout>
