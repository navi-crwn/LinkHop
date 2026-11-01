<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ trim(($pageTitle ?? '') ?: config('app.name').' - Your Destination Is A Hop Away') }}</title>
        <meta name="description" content="Manage every short link, analytics card, and quota from the LinkHop workspace.">
        <link rel="icon" href="{{ route('brand.favicon') }}">
        {{-- Apply the saved theme before first paint (no light flash in dark mode) --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                try {
                    var t = localStorage.getItem('helink-theme');
                    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>
        <x-hop::fonts />
        <script nonce="{{ Vite::cspNonce() }}" src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script nonce="{{ Vite::cspNonce() }}" src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script nonce="{{ Vite::cspNonce() }}" src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        {{-- Only used from Alpine components, which start after deferred scripts (P2). --}}
        <script nonce="{{ Vite::cspNonce() }}" defer src="https://unpkg.com/qr-code-styling@1.6.0-rc.1/lib/qr-code-styling.js"></script>
        @stack('scripts-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="app-bg min-h-screen">
        <x-session-expired-notice />
        @auth
            @php
                $user = auth()->user();
                $isAdmin = $user->isAdmin();
                $abuseCount = $isAdmin
                    ? \App\Models\AbuseReport::where('status', 'open')->count()
                        + \App\Models\AbuseFlag::where('status', 'open')->count()
                    : 0;
                $sectionTitle = trim(\Illuminate\Support\Str::after($pageTitle ?? '', ' - ')) ?: 'LinkHop';
            @endphp
            <div x-data="{ nav: false }" @keydown.escape.window="nav = false" class="lg:pl-68">
                {{-- Sidebar: floating glass panel on desktop, drawer on mobile --}}
                <div x-show="nav" x-cloak x-transition.opacity @click="nav = false" class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-xs lg:hidden"></div>
                <aside class="fixed inset-y-0 left-0 z-50 w-68 p-3 transition-transform duration-300 lg:translate-x-0"
                       :class="nav ? 'translate-x-0' : '-translate-x-full'"
                       @click="if ($event.target.closest('a')) nav = false">
                    <div class="glass flex h-full flex-col rounded-3xl">
                        <div class="flex h-16 items-center justify-between px-5">
                            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                                <x-wordmark class="h-11 w-auto" />
                                @if ($isAdmin)
                                    <span class="chip-blue">Admin</span>
                                @endif
                            </a>
                            <button type="button" class="icon-btn lg:hidden" @click="nav = false" aria-label="Close menu">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                            </button>
                        </div>
                        <div class="px-3 pb-2">
                            <button type="button" @click="$dispatch('open-palette')" class="nav-item w-full" aria-haspopup="dialog">
                                <svg class="h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                                <span class="flex-1 text-left">{{ __('Search') }}</span>
                                <kbd class="font-mono text-[11px] text-muted" x-text="/Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Ctrl K'">Ctrl K</kbd>
                            </button>
                        </div>
                        <nav class="flex-1 overflow-y-auto px-3 pb-3">
                            @include('components.partials.nav', ['isAdmin' => $isAdmin, 'abuseCount' => $abuseCount])
                        </nav>
                        <div class="border-t border-slate-500/10 px-5 py-3 text-xs text-muted">
                            <a href="{{ route('changelog') }}" class="hover:underline">{{ __('Changelog') }}</a>
                            <span aria-hidden="true"> · </span>
                            <a href="https://github.com/navi-crwn/LinkHop" rel="noopener" class="hover:underline">GitHub</a>
                        </div>
                    </div>
                </aside>

                {{-- Top bar --}}
                <header class="sticky top-0 z-30 px-3 pt-3 sm:px-5">
                    <div class="glass flex h-14 items-center gap-2 rounded-2xl px-2 sm:px-3">
                        <button type="button" class="icon-btn lg:hidden" @click="nav = true" aria-label="Open menu">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h10"/></svg>
                        </button>
                        <p class="truncate pl-1 text-sm font-semibold text-ink">{{ $sectionTitle }}</p>
                        <div class="ml-auto flex items-center gap-1.5">
                            @if (request()->routeIs('bio.*'))
                                <a href="{{ route('bio.create') }}" class="btn-primary btn-sm sm:btn">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                    <span class="hidden sm:inline">New bio</span>
                                </a>
                            @elseif (request()->routeIs('folders.index'))
                                <button type="button" data-call="openFolderModal" class="btn-primary btn-sm sm:btn">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                    <span class="hidden sm:inline">New folder</span>
                                </button>
                            @elseif (request()->routeIs('tags.index'))
                                <button type="button" data-call="openTagModal" class="btn-primary btn-sm sm:btn">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                    <span class="hidden sm:inline">New tag</span>
                                </button>
                            @else
                                <button type="button" data-call="openLinkPanel" data-fallback-href="{{ route('links.index') }}?create=1" class="create-link-trigger btn-primary btn-sm sm:btn">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                    <span class="hidden sm:inline">New link</span>
                                </button>
                            @endif
                            <x-hop::language-switcher />
                            <button type="button" data-call="toggleTheme" id="theme-toggle-button" class="icon-btn" aria-label="Toggle theme" title="Toggle theme">
                                <svg class="h-[18px] w-[18px] dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z"/></svg>
                                <svg class="hidden h-[18px] w-[18px] dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                            </button>
                            <x-hop::account-menu :user="$user" :settings-url="route('settings')" :account-url="hub_url('profile')" :logout-url="route('logout')" />
                        </div>
                    </div>
                </header>

                <main class="app-shell px-3 pb-10 pt-4 sm:px-5 sm:pt-6">
                    <div class="mx-auto w-full @yield('container-width', 'max-w-(--breakpoint-xl) 2xl:max-w-(--breakpoint-2xl)') space-y-5">
                        @isset($header)
                            <div>{{ $header }}</div>
                        @endisset
                        @hasSection('content')
                            @yield('content')
                        @else
                            {{ $slot ?? '' }}
                        @endif
                    </div>
                </main>
                <x-command-palette />
            </div>
        @else
            <div class="min-h-screen">
                <main>
                    @hasSection('content')
                        @yield('content')
                    @else
                        {{ $slot ?? '' }}
                    @endif
                </main>
            </div>
        @endauth
        <x-cookie-consent />
        <script nonce="{{ Vite::cspNonce() }}">
            window.toggleTheme = function () {
                const isDark = document.documentElement.classList.toggle('dark');
                try { localStorage.setItem('helink-theme', isDark ? 'dark' : 'light'); } catch (e) {}
                window.dispatchEvent(new CustomEvent('theme-changed'));
            };
        </script>
        @stack('scripts')
    </body>
</html>
