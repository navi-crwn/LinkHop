<x-info-page title="Help" lead="How to do the common things. General questions are in the FAQ.">
    <div class="space-y-8">
        <section class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/[0.03]">
            <h2 id="shorten" class="text-xl font-bold">{{ __('Shorten a link') }}</h2>
            <ol class="mt-4 space-y-2.5">
                <li>{!! __('Paste the long link on the <a href=":url">home page</a>.', ['url' => route('home')]) !!}</li>
                <li>{{ __('Optional: pick your own slug, a password or an expiry date.') }}</li>
                <li>{!! __('Press <strong>Shorten</strong> and copy your link, or download its QR code.') !!}</li>
            </ol>
        </section>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <section class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <h2 id="manage" class="text-lg font-bold">{{ __('See clicks and manage links') }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-white/70">{!! __('Sign in with your HOP ID and open the <a href=":url">dashboard</a>: every link with its clicks, folders and tags, and the analytics page.', ['url' => route('dashboard')]) !!}</p>
            </section>

            <section class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <h2 id="guest" class="text-lg font-bold">{{ __('Keep a guest link') }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-white/70">{{ __('Guest links are deleted after :idle days without a click, and after :max days at most. Sign up for a free HOP ID to keep links and see their stats.', ['idle' => $idleDays, 'max' => $maxDays]) }}</p>
            </section>

            <section class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <h2 id="api" class="text-lg font-bold">{{ __('Use the API') }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-white/70">{!! __('Create a token at the hub (<strong>HOP ID → Settings → API</strong>), then follow the <a href=":url">API docs</a>.', ['url' => route('pages.docs')]) !!}</p>
            </section>

            <section class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <h2 id="check" class="text-lg font-bold">{{ __('Check a link before opening it') }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-white/70">{!! __('Paste any hel.ink short link on <a href=":url">Check a link</a> to see where it goes.', ['url' => route('link.check')]) !!}</p>
            </section>

            <section class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <h2 id="report" class="text-lg font-bold">{{ __('Report a harmful link') }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-white/70">{!! __('Use the <a href=":url">report form</a>. Reports go to the moderation queue.', ['url' => route('report.create')]) !!}</p>
            </section>

            <section class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <h2 id="old" class="text-lg font-bold">{{ __('Old links') }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-white/70">{!! __('Short links moved to <code>hel.ink/l/slug</code> on 28 October 2026. The old form <code>hel.ink/slug</code> redirects there until 27 November 2026.') !!}</p>
            </section>
        </div>
    </div>
</x-info-page>
