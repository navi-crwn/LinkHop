@push('head')
<link rel="stylesheet" href="{{ asset('hop-home/hop-home.css') }}">
<script nonce="{{ Vite::cspNonce() }}" src="{{ asset('hop-home/hop-motion.js') }}" defer></script>
<script nonce="{{ Vite::cspNonce() }}" src="{{ asset('hop-home/hop-home.js') }}" defer></script>
@endpush
<x-marketing-layout :title="'LinkHop · '.__('Short links')" :themed="true">
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
                {{-- Row 1: link + Shorten. Row 2: advanced options and sign-up hint; the security check
                     (normal size) right under Shorten, shown only when Cloudflare asks for a click. --}}
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label for="target_url" class="sr-only">{{ __('Long URL') }}</label>
                    <input id="target_url" type="url" name="target_url" value="{{ old('target_url') }}" required
                        placeholder="{{ __('Paste a long link…') }}"
                        class="min-w-0 flex-1 rounded-2xl border-0 bg-slate-100 px-4 py-3.5 text-[15px] text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-indigo-400/50 dark:bg-white/6 dark:text-white dark:placeholder-white/35 dark:focus:bg-white/9">
                    <button type="submit"
                        class="rounded-2xl bg-linear-to-r from-indigo-500 via-sky-500 to-fuchsia-500 px-6 py-3.5 font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:brightness-110 active:scale-[0.98] disabled:opacity-50">
                        <span x-show="!waiting">{{ __('Shorten') }}</span><span x-cloak x-show="waiting">{{ __('Verifying…') }}</span>
                    </button>
                </div>
                <div class="flex flex-col-reverse px-2 pb-1 pt-2 sm:flex-row sm:items-start sm:gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs"
                            x-bind:class="challenge && 'sm:flex-col sm:items-start sm:pt-2 sm:gap-y-3'">
                            <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-300">{{ __('Create free account') }} →</a>
                        </div>
                        <details id="guest-advanced" class="mt-2" @if(old('slug') || old('content_warning', 'none') !== 'none' || old('warning_reason') || $errors->hasAny(['slug', 'content_warning', 'warning_reason'])) open @endif>
                            <summary class="cursor-pointer py-2 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-white/70 dark:hover:text-white">{{ __('Advanced') }}</summary>
                            <label for="slug" class="label">{{ __('Custom slug (optional)') }}</label>
                            <div class="flex items-center gap-2">
                            <span class="text-sm text-slate-400 dark:text-white/45">{{ public_host() }}/l/</span>
                            <input id="slug" type="text" name="slug" value="{{ old('slug') }}" placeholder="my-link" pattern="[A-Za-z0-9\-]+"
                                class="min-w-0 flex-1 rounded-xl border-0 bg-slate-100 px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-400/50 dark:bg-white/6 dark:text-white dark:placeholder-white/30">
                            </div>
                            <div class="py-4"><x-content-warning-fields prefix="guest-warning" /></div>
                        </details>
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
                            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-white/60">
                        {{ __('Guest links expire after :idle days without a click or :max days in total.', ['idle' => $idleDays, 'max' => $maxDays]) }}
                        <a href="{{ route('register') }}" class="font-semibold text-indigo-600 underline-offset-2 hover:underline dark:text-white">{{ __('Create free account') }}</a>
                    </p>
                </div>
            @else
                <p class="px-3 pb-2 text-[11px] text-slate-500 dark:text-white/60">
                    {{ __('Guest links expire after :idle days without a click or :max days in total.', ['idle' => $idleDays, 'max' => $maxDays]) }}
                </p>
            @endif
        </div>
        <p class="h-chip">LinkHop · hel.ink</p>
        <h1 id="link-title">{{ __('Small URL.') }} <em>{{ __('Serious possibilities.') }}</em></h1>
        <p class="h-lead">{{ __('Turn a long link into one that is short, trackable and safe to share.') }}</p>
        <div class="h-cta"><a class="h-pill" href="#link-tour">{{ __('Take the interactive tour') }} ↓</a><a class="h-pill" href="{{ route('link.check') }}">{{ __('Check a link') }}</a></div>
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
