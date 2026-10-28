@php
    $marketingLinks = [
        ['label' => 'Features', 'href' => route('home').'#features'],
        ['label' => 'Link in Bio', 'href' => route('home').'#bio'],
        ['label' => 'Check a link', 'href' => route('link.check')],
        ['label' => 'Products', 'href' => route('products')],
        ['label' => 'Help', 'href' => route('help')],
        ['label' => 'FAQ', 'href' => route('faq')],
    ];
@endphp
<header class="sticky top-0 z-40 px-3 pt-3 sm:px-5" x-data="{ open: false }" @keydown.escape.window="open = false">
    <nav class="mx-auto flex h-14 max-w-6xl items-center justify-between rounded-2xl border border-slate-200/80 bg-white/70 px-3 shadow-xs shadow-slate-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/50 dark:shadow-none sm:px-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-slate-900 dark:text-white">
            <x-wordmark class="h-11 w-auto" />
        </a>
        <div class="hidden items-center gap-1 text-sm text-slate-600 md:flex dark:text-white/70">
            @foreach ($marketingLinks as $item)
                <a href="{{ $item['href'] }}" class="rounded-lg px-3 py-1.5 transition hover:bg-slate-900/5 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white">{{ $item['label'] }}</a>
            @endforeach
        </div>
        <div class="flex items-center gap-2 text-sm">
            @if (! empty($themed))
                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-900/5 dark:text-white/80 dark:hover:bg-white/10" aria-label="Toggle theme" title="Toggle theme"
                    data-toggle-dark>
                    <svg class="h-[18px] w-[18px] dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z"/></svg>
                    <svg class="hidden h-[18px] w-[18px] dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>
            @endif
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-xl bg-slate-900 px-3.5 py-1.5 font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-white/90">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="hidden rounded-xl px-3 py-1.5 text-slate-600 transition hover:text-slate-900 sm:inline-block dark:text-white/80 dark:hover:text-white">Log in</a>
                <a href="{{ route('register') }}" class="rounded-xl bg-slate-900 px-3.5 py-1.5 font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-white/90">Sign up</a>
            @endauth
            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-900/5 md:hidden dark:text-white/80 dark:hover:bg-white/10" @click="open = !open" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path x-show="!open" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/><path x-show="open" x-cloak stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
    </nav>
    <div x-show="open" x-cloak x-transition.origin.top @click.outside="open = false" class="mx-auto mt-2 max-w-6xl rounded-2xl border border-slate-200/80 bg-white/90 p-2 dark:border-white/10 dark:bg-slate-900/80 backdrop-blur-xl md:hidden">
        @foreach ($marketingLinks as $item)
            <a href="{{ $item['href'] }}" @click="open = false" class="block rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white/80 dark:hover:bg-white/10 dark:hover:text-white">{{ $item['label'] }}</a>
        @endforeach
        @guest
            <a href="{{ route('login') }}" class="block rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-900/5 hover:text-slate-900 dark:text-white/80 dark:hover:bg-white/10 dark:hover:text-white">Log in</a>
        @endguest
    </div>
</header>
