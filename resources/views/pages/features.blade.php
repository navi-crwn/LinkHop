<x-info-page title="Features" lead="Everything is included for every account. No premium tier, no locked features." :wide="true">
    {{-- Bento grid for features --}}
    <div class="not-prose grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 [&>div]:min-w-0 [&>div:first-child]:sm:col-span-2 [&>div:last-child]:lg:col-span-2">
        {{-- Card 1: Short links --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white/80 p-6 shadow-sm transition hover:shadow-md dark:border-white/10 dark:bg-white/[0.03]">
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </div>
            <h2 id="links" class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Short links') }}</h2>
            <x-feature-demo name="links" label="Short links" />
            <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-white/70">
                <li>{!! __('<strong>Random or custom slugs</strong> at <code>hel.ink/l/your-slug</code>.') !!}</li>
                <li>{!! __('<strong>Password and expiry date</strong> per link, when you need them.') !!}</li>
                <li>{!! __('<strong>QR codes</strong> for every link, as PNG, SVG or JPG.') !!}</li>
                <li>{!! __('<strong>No account needed</strong> to shorten. Guest links are deleted after :idle days without a click, and after :max days at most.', ['idle' => $idleDays, 'max' => $maxDays]) !!}</li>
            </ul>
        </div>

        {{-- Card 2: Analytics --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white/80 p-6 shadow-sm transition hover:shadow-md dark:border-white/10 dark:bg-white/[0.03]">
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg>
            </div>
            <h2 id="analytics" class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Analytics') }}</h2>
            <x-feature-demo name="analytics" label="Analytics" />
            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-white/70">{{ __('Clicks, unique visitors, countries and cities, devices, browsers and referrers, in real time, with CSV export. Visitors are counted with a keyed hash of their IP address, never the address itself.') }}</p>
        </div>

        {{-- Card 3: Organize --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white/80 p-6 shadow-sm transition hover:shadow-md dark:border-white/10 dark:bg-white/[0.03]">
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            </div>
            <h2 id="organize" class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Organize') }}</h2>
            <x-feature-demo name="organize" label="Organize" />
            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-white/70">{{ __('Folders and tags, search, bulk actions, and comments on a link.') }}</p>
        </div>

        {{-- Card 4: Safety --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white/80 p-6 shadow-sm transition hover:shadow-md dark:border-white/10 dark:bg-white/[0.03]">
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <h2 id="safety" class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Safety') }}</h2>
            <x-feature-demo name="safety" label="Safety" />
            <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-white/70">
                <li>{!! __('<strong>Link check</strong>: see where a short link goes before you open it, at <a href=":url">Check a link</a>.', ['url' => route('link.check')]) !!}</li>
                <li>{!! __('<strong>Abuse protection</strong>: rate limits, a domain blacklist, proxy and VPN checks, an hourly scan for phishing and scams, and a <a href=":url">report form</a>.', ['url' => route('report.create')]) !!}</li>
            </ul>
        </div>

        {{-- Card 5: API --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white/80 p-6 shadow-sm transition hover:shadow-md dark:border-white/10 dark:bg-white/[0.03]">
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            </div>
            <h2 id="api" class="text-lg font-bold text-slate-900 dark:text-white">{{ __('API and ShareX') }}</h2>
            <x-feature-demo name="api" label="API and ShareX" />
            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-white/70">{!! __('A REST API with scoped tokens and a ready-made ShareX config. See the <a href=":url">API docs</a>.', ['url' => route('pages.docs')]) !!}</p>
        </div>

        {{-- Card 6: One account --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white/80 p-6 shadow-sm transition hover:shadow-md dark:border-white/10 dark:bg-white/[0.03]">
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </div>
            <h2 id="account" class="text-lg font-bold text-slate-900 dark:text-white">{{ __('One account') }}</h2>
            <x-feature-demo name="account" label="One account" />
            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-white/70">{!! __('Sign in with your free <a href="https://hel.ink">HOP ID</a>, the same account as BioHop, PixelHop and NoteHop, with two-factor sign-in and a login history.') !!}</p>
        </div>
    </div>

    <div class="not-prose mt-12 text-center">
        <a class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-sky-600 to-indigo-600 px-8 py-4 font-semibold text-white shadow-lg shadow-sky-600/20 transition hover:from-sky-500 hover:to-indigo-500" href="{{ route('home') }}">
            {{ __('Shorten a link') }} &rarr;
        </a>
    </div>
</x-info-page>
