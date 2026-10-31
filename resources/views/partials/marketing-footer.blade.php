@php
    $footerBlocks = [
        [
            'title' => 'LinkHop',
            'items' => [
                ['label' => 'About', 'route' => route('pages.about')],
                ['label' => 'Features', 'route' => route('pages.features')],
                ['label' => 'API', 'route' => route('pages.docs')],
                ['label' => 'Status', 'route' => route('pages.status')],
            ],
        ],
        [
            'title' => 'Support',
            'items' => [
                ['label' => 'Help', 'route' => route('pages.help')],
                ['label' => 'FAQ', 'route' => route('pages.faq')],
                ['label' => 'Contact', 'route' => route('pages.contact')],
                ['label' => 'Request a feature', 'route' => 'https://github.com/navi-crwn/LinkHop/issues/new/choose', 'external' => true],
            ],
        ],
        [
            'title' => 'Policies',
            'items' => [
                ['label' => 'Privacy', 'route' => route('pages.privacy')],
                ['label' => 'Terms', 'route' => route('pages.terms')],
                ['label' => 'Check a link', 'route' => route('link.check')],
                ['label' => 'Report abuse', 'route' => route('report.create')],
            ],
        ],
        [
            // The HOP family, from the hub's asset manifest (hop/ui), so every app lists the same apps.
            'title' => 'HOP family',
            'items' => collect(\Hop\Ui\Assets::family('linkhop'))
                ->reject(fn ($app) => $app['current'])
                ->map(fn ($app) => ['label' => $app['name'], 'route' => $app['url'], 'external' => true])
                ->values()->all(),
        ],
    ];
@endphp
<footer class="mt-16 px-3 pb-6 sm:px-5">
    <div class="mx-auto max-w-6xl rounded-3xl border border-slate-200/80 bg-white/60 p-6 text-sm text-slate-600 backdrop-blur-xl dark:border-white/10 dark:bg-white/3 dark:text-white/60 sm:p-8">
        <div class="grid grid-cols-2 gap-8 lg:grid-cols-5">
            <div class="col-span-2 lg:col-span-1">
                <div class="flex items-center gap-2.5">
                    <x-wordmark class="h-10 w-auto" />
                </div>
                <p class="mt-3 text-[13px] leading-relaxed">Short links with analytics and QR codes. Part of the HOP family. Free and open source.</p>
            </div>
            @foreach ($footerBlocks as $block)
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-white/60">{{ $block['title'] }}</p>
                    <ul class="mt-3 space-y-2 text-[13px]">
                        @foreach ($block['items'] as $item)
                            <li><a href="{{ $item['route'] }}" @if (! empty($item['external'])) target="_blank" rel="noopener" @endif class="transition hover:text-slate-900 dark:hover:text-white">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <p class="mt-8 border-t border-slate-200 pt-5 text-xs text-slate-500 dark:border-white/10 dark:text-white/60">© {{ date('Y') }} LinkHop · <a href="https://db-ip.com" target="_blank" rel="noopener" class="hover:text-slate-900 dark:hover:text-white">IP Geolocation by DB-IP</a></p>
    </div>
</footer>
