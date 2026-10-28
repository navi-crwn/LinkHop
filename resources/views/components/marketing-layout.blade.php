@props(['title' => null, 'themed' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['h-full', 'dark' => ! $themed])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $metaTitle = $seoSettings->site_title ?? ($title ?? config('app.name'));
            $metaDescription = $seoSettings->meta_description ?? 'LinkHop keeps hel.ink lightweight for guests and teams.';
            $metaKeywords = $seoSettings->meta_keywords ?? 'shortlink, helink, qr, analytics';
            $ogTitle = $seoSettings->og_title ?? $metaTitle;
            $ogDescription = $seoSettings->og_description ?? $metaDescription;
            $ogImage = $seoSettings->og_image ?? route('brand.logo.dark');
            $favicon = $seoSettings->favicon ?? route('brand.favicon');
        @endphp
        <title>{{ $metaTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        <meta name="keywords" content="{{ $metaKeywords }}">
        <meta property="og:title" content="{{ $ogTitle }}">
        <meta property="og:description" content="{{ $ogDescription }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <link rel="icon" href="{{ $favicon }}">
        @if ($themed)
            {{-- Apply the saved theme before first paint (same key as the app shell) --}}
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
        @endif
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|instrument-serif:400i&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @if ($themed)
    <body class="min-h-full bg-slate-50 font-sans text-slate-900 antialiased dark:bg-[#0e1322] dark:text-slate-100">
        <x-session-expired-notice />
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -left-40 -top-48 h-136 w-136 rounded-full bg-indigo-300/40 blur-[120px] dark:bg-indigo-500/20"></div>
            <div class="absolute -right-32 top-10 h-112 w-md rounded-full bg-sky-200/50 blur-[120px] dark:bg-sky-500/10"></div>
            <div class="absolute -bottom-56 left-1/3 h-120 w-120 rounded-full bg-fuchsia-200/40 blur-[130px] dark:bg-fuchsia-500/10"></div>
        </div>
    @else
    <body class="min-h-full bg-[#070a17] font-sans text-white antialiased">
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -left-40 -top-40 h-136 w-136 rounded-full bg-indigo-600/30 blur-[120px]"></div>
            <div class="absolute -right-32 top-20 h-112 w-md rounded-full bg-sky-500/20 blur-[120px]"></div>
            <div class="absolute -bottom-48 left-1/3 h-120 w-120 rounded-full bg-fuchsia-600/20 blur-[130px]"></div>
        </div>
    @endif
        @include('partials.marketing-nav', ['themed' => $themed])
        <main>
            {{ $slot }}
        </main>
        @include('partials.marketing-footer')
        @stack('scripts')
    </body>
</html>
