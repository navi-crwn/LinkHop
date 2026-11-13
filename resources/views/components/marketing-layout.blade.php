@props(['title' => null, 'themed' => false, 'description' => null, 'index' => true])
{{-- $index false: an error or link page (404, 429, link missing or ended), kept out of search results. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" @class(['h-full', 'dark' => ! $themed])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $metaTitle = $title ?? config('app.name');
            $metaDescription = $description ?? __('Shorten long links for free: your own short address, a QR code for every link and click stats. Start without an account; LinkHop is open source.');
        @endphp
        <title>{{ $metaTitle }}</title>
        <meta name="keywords" content="shortlink, helink, qr, analytics">
        @include('partials.seo-head', ['title' => $metaTitle, 'description' => $metaDescription, 'index' => $index])
        <link rel="icon" href="{{ route('brand.favicon') }}">
        @if ($themed)
            {{-- Apply the saved theme before first paint (the same reader as the app shell) --}}
            @include('partials.theme-boot')
        @endif
        <x-hop::fonts />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
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
        <main id="main">
            {{ $slot }}
        </main>
        @include('partials.marketing-footer')
        @stack('scripts')
    </body>
</html>
