<x-app-layout>
    <x-slot name="pageTitle">{{ __('Short links moved') }}</x-slot>
    @php
        $until = \App\Support\ShortLinkMove::untilLabel();
        $host = public_host();
        // The two address forms and the other bold values, built once so every sentence below can be translated whole.
        $new = '<strong class="text-ink">'.e($host).'/l/<em>slug</em></strong>';
        $old = '<strong class="text-ink">'.e($host).'/<em>slug</em></strong>';
    @endphp

    <x-page-header :title="__('Short links moved to :host/l/…', ['host' => $host])" :subtitle="__('hel.ink is becoming the home of several HOP apps. Short links get their own path.')" />

    <section class="glass-card space-y-5 text-sm leading-relaxed text-ink">
        <div>
            <h2 class="section-title">{{ __('What changed') }}</h2>
            <p class="mt-2 text-muted">{!! __('Every short link is now reached at :new instead of :old. Nothing was deleted: your links, their slugs, clicks and statistics are the same. The dashboard, QR codes and the API already show the new form.', ['new' => $new, 'old' => $old]) !!}</p>
        </div>
        <div>
            <h2 class="section-title">{{ __('Until :date', ['date' => $until]) }}</h2>
            <p class="mt-2 text-muted">{!! __('The old form keeps working: :old redirects to :new, and those visits count as clicks like any other.', ['old' => $old, 'new' => $new]) !!}</p>
        </div>
        <div>
            <h2 class="section-title">{{ __('From :date', ['date' => $until]) }}</h2>
            <p class="mt-2 text-muted">{!! __('The old form stops. :old no longer opens your link; only :new does.', ['old' => $old, 'new' => $new]) !!}</p>
        </div>
        <div>
            <h2 class="section-title">{{ __('What to do') }}</h2>
            <ul class="mt-2 list-disc space-y-1 ps-5 text-muted">
                <li>{!! __('Replace the links you have published elsewhere (posts, bios, printed material) with the /l/ form. :mylinks shows every link in its new form, ready to copy.', ['mylinks' => '<a href="'.e(route('links.index')).'" class="font-medium text-indigo-600 hover:underline dark:text-indigo-300">'.e(__('My links')).'</a>']) !!}</li>
                <li>{{ __('Print or download QR codes again where they were made before this change; a QR code made from now on already points at the new form.') }}</li>
                <li>{!! __('Bio pages are not affected: they stay at :bio.', ['bio' => '<strong class="text-ink">'.e($host).'/b/<em>name</em></strong>']) !!}</li>
                <li>{!! __('API clients get the new form in :field; nothing to change on your side unless you built the address yourself.', ['field' => '<code>short_url</code>']) !!}</li>
            </ul>
        </div>
        <p class="text-xs text-muted">{!! __('We also emailed this notice to every account that owns links, and will send a reminder a week before :date. See the :terms, section 11A.', ['date' => e($until), 'terms' => '<a href="'.e(hub_url('terms')).'" class="underline">'.e(__('Terms of Service')).'</a>']) !!}</p>
    </section>
</x-app-layout>
