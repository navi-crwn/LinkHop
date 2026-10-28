@php
    $footerBlocks = [
        [
            'title' => 'Company',
            'items' => [
                ['label' => 'About', 'route' => route('about')],
                ['label' => 'Contact', 'route' => route('contact')],
                ['label' => 'FAQ', 'route' => route('faq')],
                ['label' => 'Help Center', 'route' => route('help')],
            ],
        ],
        [
            'title' => 'Policies',
            'items' => [
                ['label' => 'Privacy policy', 'route' => route('privacy')],
                ['label' => 'Terms of service', 'route' => route('terms')],
                ['label' => 'Usage tiers', 'route' => route('plans')],
                ['label' => 'Request a feature', 'route' => route('feature.requests')],
            ],
        ],
        [
            'title' => 'Explore',
            'items' => [
                ['label' => 'Products', 'route' => route('products')],
                ['label' => 'Check a link', 'route' => route('link.check')],
                ['label' => 'Report abuse', 'route' => route('report.create')],
                ['label' => 'Features', 'route' => route('home').'#features'],
                ['label' => 'Analytics', 'route' => route('products').'#analytics-feature'],
            ],
        ],
        [
            'title' => 'LinkHop Family',
            'items' => [
                ['label' => 'PixelHop - Image Hosting', 'route' => 'https://p.hel.ink', 'external' => true],
                ['label' => 'Image Tools', 'route' => 'https://p.hel.ink/features.php', 'external' => true],
                ['label' => 'NoteHop - Encrypted Notes', 'route' => 'https://note.hel.ink', 'external' => true],
            ],
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
                <p class="mt-3 text-[13px] leading-relaxed">Short links & Link in Bio. Free and open source.</p>
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
