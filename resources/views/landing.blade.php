@push('head')
<link rel="stylesheet" href="{{ asset('hop-home/hop-bento.css') }}?v={{ substr(md5_file(public_path('hop-home/hop-bento.css')), 0, 8) }}">
<link rel="stylesheet" href="{{ asset('hop-home/hop-home.css') }}?v={{ substr(md5_file(public_path('hop-home/hop-home.css')), 0, 8) }}">
<script nonce="{{ Vite::cspNonce() }}" src="{{ asset('hop-home/hop-motion.js') }}?v={{ substr(md5_file(public_path('hop-home/hop-motion.js')), 0, 8) }}" defer></script>
<script nonce="{{ Vite::cspNonce() }}" src="{{ asset('hop-home/hop-home.js') }}?v={{ substr(md5_file(public_path('hop-home/hop-home.js')), 0, 8) }}" defer></script>
<script nonce="{{ Vite::cspNonce() }}" src="{{ asset('hop-home/hop-hero3d.js') }}?v={{ substr(md5_file(public_path('hop-home/hop-hero3d.js')), 0, 8) }}" defer></script>
@vite(['resources/css/home.css', 'resources/js/home.js'])
{{-- Structured data (not a script that runs): this page is the shortener itself. --}}
<script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! \App\Support\Seo::json(\App\Support\Seo::webApplication('LinkHop', __('Shorten long links for free: your own short address, a QR code for every link and click stats. Start without an account; LinkHop is open source.'), \App\Support\Seo::url())) !!}</script>
@endpush
<x-marketing-layout :title="'LinkHop · '.__('Free link shortener with QR codes and click stats')" :themed="true">
    @php
        $idleDays = config('shortener.guest_idle_days', 14);
        $maxDays = config('shortener.guest_max_days', 30);
        $card = 'rounded-3xl border border-slate-200/80 bg-white/80 shadow-xs shadow-slate-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-white/5 dark:shadow-none';
        $sceneCopy = collect(['A bunny has a long link to share', 'Hop to hel.ink…', 'hel.ink makes it short', 'On to the destination', 'Arrived ✓', 'Two bunnies, far apart', 'One sends a link; hel.ink makes it short', 'The other gets :link', 'This link has a password', 'Password :dots', 'Unlocked ✓', 'Back home for the next link', 'Wrong password ✕', 'No hop this time'])->mapWithKeys(fn ($text) => [$text => __($text)])->all();
    @endphp

    <div class="product-showcase link-showcase hop-home">
    <section class="h-hero h-tool" aria-labelledby="link-title">
        <div class="min-w-0">
        <div class="h-tool-box {{ $card }} p-2 text-left shadow-xl shadow-indigo-500/10 ring-1 ring-indigo-500/10 dark:shadow-2xl dark:shadow-black/30 dark:ring-0">
            <form
                x-data="turnstileForm(@js(config('services.turnstile.site_key')))"
                x-on:submit="handleSubmit($event)"
                method="POST"
                action="{{ route('shorten.store') }}"
            >
                @csrf
                {{-- Row 1: the link and Shorten, one height and one radius (control standard). Row 2: the security
                     check, a row of its own that opens (200 ms) only when Cloudflare asks for a click; 300 px wide,
                     so it never overlaps the rows below. Row 3: Advanced on the left, sign-up on the right. --}}
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label for="target_url" class="sr-only">{{ __('Long URL') }}</label>
                    <input id="target_url" type="url" name="target_url" value="{{ old('target_url') }}" required
                        placeholder="{{ __('Paste a long link…') }}"
                        class="h-12 min-w-0 flex-1 rounded-2xl border-0 bg-slate-100 px-4 text-[15px] text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-indigo-400/50 dark:bg-white/6 dark:text-white dark:placeholder-white/35 dark:focus:bg-white/9">
                    <button type="submit"
                        class="h-12 rounded-2xl bg-linear-to-r from-indigo-500 via-sky-500 to-fuchsia-500 px-6 font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:brightness-110 active:scale-[0.97] disabled:opacity-50">
                        <span x-show="!waiting">{{ __('Shorten') }}</span><span x-cloak x-show="waiting">{{ __('Verifying…') }}</span>
                    </button>
                </div>
                {{-- Kept rendered (Turnstile needs a live container); the row grows from 0 to its height when a challenge or an error shows. --}}
                <div class="grid px-2 transition-[grid-template-rows] duration-200 ease-out motion-reduce:transition-none"
                    x-bind:class="challenge || status === 'error' ? 'grid-rows-[1fr] pt-3' : 'grid-rows-[0fr]'">
                    <div class="min-h-0 overflow-hidden">
                        <x-turnstile class="w-[300px] max-w-full max-sm:mx-auto" />
                    </div>
                </div>
                <div class="relative grid grid-cols-[1fr_auto] items-start px-2 pt-1">
                    <details id="guest-advanced" class="group col-span-2 col-start-1 row-start-1 border-b-0! py-0!" @if(old('slug') || old('content_warning', 'none') !== 'none' || old('warning_reason') || $errors->hasAny(['slug', 'content_warning', 'warning_reason'])) open @endif>
                        <summary class="flex h-10 cursor-pointer items-center text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-white/70 dark:hover:text-white">{{ __('Advanced') }}<svg class="ml-1.5 size-3.5 transition-transform duration-150 ease-out group-open:rotate-90 motion-reduce:transition-none" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg></summary>
                        <label for="slug" class="label">{{ __('Custom slug (optional)') }}</label>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-slate-400 dark:text-white/45">{{ public_host() }}/l/</span>
                            <input id="slug" type="text" name="slug" value="{{ old('slug') }}" placeholder="my-link" pattern="[A-Za-z0-9\-]{3,48}" title="{{ __('Slug can only contain letters, numbers, and hyphens.') }}"
                                class="h-10 min-w-0 flex-1 rounded-xl border-0 bg-slate-100 px-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-400/50 dark:bg-white/6 dark:text-white dark:placeholder-white/30">
                        </div>
                        <div class="py-4"><x-content-warning-fields prefix="guest-warning" /></div>
                    </details>
                    <a href="{{ route('register') }}" class="relative z-10 col-start-2 row-start-1 flex h-10 items-center text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-300">{{ __('Create free account') }} →</a>
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
                            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-white/60">
                        {{ __('Guest links expire after :idle days without a click or :max days in total.', ['idle' => $idleDays, 'max' => $maxDays]) }}
                        <a href="{{ route('register') }}" class="font-semibold text-indigo-600 underline-offset-2 hover:underline dark:text-white">{{ __('Create free account') }}</a>
                    </p>
                </div>
            @else
                <p class="mx-2 mt-1 border-t border-slate-200/80 px-0 pb-2 pt-3 text-xs text-slate-500 dark:border-white/10 dark:text-white/60">
                    {{ __('Guest links expire after :idle days without a click or :max days in total.', ['idle' => $idleDays, 'max' => $maxDays]) }}
                </p>
            @endif
        </div>
        <p class="h-chip">LinkHop · hel.ink</p>
        <h1 id="link-title">{{ __('Small URL.') }} <em>{{ __('Serious possibilities.') }}</em></h1>
        <p class="h-lead">{{ __('Turn a long link into one that is short, trackable and safe to share.') }}</p>
        <div class="h-cta"><a class="h-pill" href="#link-tour">{{ __('Take the interactive tour') }} ↓</a><a class="h-pill" href="{{ route('link.check') }}">{{ __('Check a link') }}</a></div>
        <ul class="lx-hero-facts" aria-label="{{ __('What you get') }}"><li><b>{{ __('Free forever') }}</b><span>{{ __('No paid plan, no locked feature.') }}</span></li><li><b>{{ __('No tracking') }}</b><span>{{ __('Clicks are counted, people are not followed.') }}</span></li><li><b>QR</b><span>{{ __('A print-ready code for every link.') }}</span></li></ul>
        </div>
        <div class="link-world" data-hop-scene data-mini data-copy="{{ json_encode($sceneCopy) }}" data-rotate-label="{{ __('Rotate scene') }}">
            <p class="showcase-kicker">{{ __('A small link. A whole world.') }}</p>
            <div class="link-world-canvas" data-hop-canvas><img data-hop-fallback src="{{ route('brand.asset', 'linkhop-mark-gradient.svg') }}" alt="" width="160" height="160"></div>
            <p class="link-world-caption" data-hop-story>{{ __('A bunny takes your link from here to anywhere.') }}</p>
            <div class="link-world-controls" role="group" aria-label="{{ __('Play a scenario') }}">
                @foreach (['hop' => 'Hop to a link', 'share' => 'Share a link', 'pass-ok' => 'Password: right', 'pass-fail' => 'Password: wrong'] as $key => $label)
                    <button type="button" data-hop-play="{{ $key }}" aria-pressed="false">{{ __($label) }}</button>
                @endforeach
                <button type="button" data-hop-pause aria-pressed="false">{{ __('Pause motion') }}</button>
            </div>
            <p class="showcase-caption">{{ __('Move your pointer or touch the islands. Choose a story to play.') }}</p>
        </div>
    </section>

    @vite('resources/js/hop-hero.js')
    @include('partials.home-experience')
    </div>
</x-marketing-layout>
