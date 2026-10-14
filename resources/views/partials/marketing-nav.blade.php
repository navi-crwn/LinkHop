@php
    $marketingLinks = [
        ['label' => 'Features', 'href' => route('home').'#features'],
        ['label' => 'Link in Bio', 'href' => route('home').'#bio'],
        ['label' => 'Products', 'href' => route('products')],
        ['label' => 'Help', 'href' => route('help')],
        ['label' => 'FAQ', 'href' => route('faq')],
    ];
@endphp
<header class="sticky top-0 z-40 px-3 pt-3 sm:px-5" x-data="{ open: false }" @keydown.escape.window="open = false">
    <nav class="mx-auto flex h-14 max-w-6xl items-center justify-between rounded-2xl border border-white/10 bg-slate-900/50 px-3 backdrop-blur-xl sm:px-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-white">
            <img src="{{ route('brand.logo') }}" alt="HEL.ink logo" class="h-7 w-7" />
            <span class="text-[15px] font-semibold tracking-tight">HEL.ink</span>
        </a>
        <div class="hidden items-center gap-1 text-sm text-white/70 md:flex">
            @foreach ($marketingLinks as $item)
                <a href="{{ $item['href'] }}" class="rounded-lg px-3 py-1.5 transition hover:bg-white/10 hover:text-white">{{ $item['label'] }}</a>
            @endforeach
        </div>
        <div class="flex items-center gap-2 text-sm">
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-xl bg-white px-3.5 py-1.5 font-semibold text-slate-900 transition hover:bg-white/90">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="hidden rounded-xl px-3 py-1.5 text-white/80 transition hover:text-white sm:inline-block">Log in</a>
                <a href="{{ route('register') }}" class="rounded-xl bg-white px-3.5 py-1.5 font-semibold text-slate-900 transition hover:bg-white/90">Sign up</a>
            @endauth
            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-white/80 hover:bg-white/10 md:hidden" @click="open = !open" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path x-show="!open" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/><path x-show="open" x-cloak stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
    </nav>
    <div x-show="open" x-cloak x-transition.origin.top @click.outside="open = false" class="mx-auto mt-2 max-w-6xl rounded-2xl border border-white/10 bg-slate-900/80 p-2 backdrop-blur-xl md:hidden">
        @foreach ($marketingLinks as $item)
            <a href="{{ $item['href'] }}" @click="open = false" class="block rounded-xl px-3 py-2.5 text-sm text-white/80 hover:bg-white/10 hover:text-white">{{ $item['label'] }}</a>
        @endforeach
        @guest
            <a href="{{ route('login') }}" class="block rounded-xl px-3 py-2.5 text-sm text-white/80 hover:bg-white/10 hover:text-white">Log in</a>
        @endguest
    </div>
</header>
