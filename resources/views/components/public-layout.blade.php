@props(['title' => null])
{{-- Shell for the few public pages left in this app (the link checker); the home page and marketing pages are the hub's. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name') }}</title>
        <meta name="description" content="LinkHop: short links, QR codes and click analytics, a HOP app.">
        <link rel="icon" href="{{ route('brand.favicon') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-[#070a17] font-sans text-white antialiased">
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -left-40 -top-40 h-136 w-136 rounded-full bg-indigo-600/30 blur-[120px]"></div>
            <div class="absolute -right-32 top-20 h-112 w-md rounded-full bg-sky-500/20 blur-[120px]"></div>
        </div>
        <header class="px-3 pt-3 sm:px-5">
            <nav class="mx-auto flex h-14 max-w-6xl items-center justify-between rounded-2xl border border-white/10 bg-slate-900/50 px-3 backdrop-blur-xl sm:px-4">
                <a href="{{ public_url() }}" class="flex items-center gap-2.5 text-white"><x-wordmark class="h-11 w-auto" /></a>
                <div class="flex items-center gap-2 text-sm">
                    <x-hop::badge />
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-xl bg-white px-3.5 py-1.5 font-semibold text-slate-900 hover:bg-white/90">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-xl bg-white px-3.5 py-1.5 font-semibold text-slate-900 hover:bg-white/90">Log in</a>
                    @endauth
                </div>
            </nav>
        </header>
        <main>
            {{ $slot }}
        </main>
        <footer class="mx-auto max-w-6xl px-4 py-8 text-center text-xs text-white/50">
            <a href="{{ hub_url('terms') }}" class="hover:text-white">Terms</a> · <a href="{{ hub_url('privacy') }}" class="hover:text-white">Privacy</a> · <a href="{{ route('report.create') }}" class="hover:text-white">Report abuse</a> · <a href="{{ hub_url('help') }}" class="hover:text-white">Help</a>
        </footer>
        <x-cookie-consent />
    </body>
</html>
