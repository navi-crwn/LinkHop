<x-app-layout>
    <x-slot name="pageTitle">Short links moved - {{ config('app.name') }}</x-slot>
    @php $until = \App\Support\ShortLinkMove::untilLabel(); $host = public_host(); @endphp

    <x-page-header title="Short links moved to {{ $host }}/l/…" subtitle="hel.ink is becoming the home of several HOP apps. Short links get their own path." />

    <section class="glass-card space-y-5 text-sm leading-relaxed text-ink">
        <div>
            <h2 class="section-title">What changed</h2>
            <p class="mt-2 text-muted">Every short link is now reached at <strong class="text-ink">{{ $host }}/l/<em>slug</em></strong> instead of <strong class="text-ink">{{ $host }}/<em>slug</em></strong>. Nothing was deleted: your links, their slugs, clicks and statistics are the same. The dashboard, QR codes and the API already show the new form.</p>
        </div>
        <div>
            <h2 class="section-title">Until {{ $until }}</h2>
            <p class="mt-2 text-muted">The old form keeps working: <strong class="text-ink">{{ $host }}/<em>slug</em></strong> redirects to <strong class="text-ink">{{ $host }}/l/<em>slug</em></strong>, and those visits count as clicks like any other.</p>
        </div>
        <div>
            <h2 class="section-title">From {{ $until }}</h2>
            <p class="mt-2 text-muted">The old form stops. <strong class="text-ink">{{ $host }}/<em>slug</em></strong> no longer opens your link; only <strong class="text-ink">{{ $host }}/l/<em>slug</em></strong> does.</p>
        </div>
        <div>
            <h2 class="section-title">What to do</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-muted">
                <li>Replace the links you have published elsewhere (posts, bios, printed material) with the <code>/l/</code> form. <a href="{{ route('links.index') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-300">My links</a> shows every link in its new form, ready to copy.</li>
                <li>Print or download QR codes again where they were made before this change; a QR code made from now on already points at the new form.</li>
                <li>Bio pages are not affected: they stay at <strong class="text-ink">{{ $host }}/b/<em>name</em></strong>.</li>
                <li>API clients get the new form in <code>short_url</code>; nothing to change on your side unless you built the address yourself.</li>
            </ul>
        </div>
        <p class="text-xs text-muted">We also emailed this notice to every account that owns links, and will send a reminder a week before {{ $until }}. See the <a href="{{ route('tos') }}" class="underline">Terms of Service</a>, section 11A.</p>
    </section>
</x-app-layout>
