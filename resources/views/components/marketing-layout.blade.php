<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $metaTitle = $seoSettings->site_title ?? ($title ?? config('app.name'));
            $metaDescription = $seoSettings->meta_description ?? 'HEL.ink keeps hel.ink lightweight for guests and teams.';
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
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-[#070a17] font-sans text-white antialiased">
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -left-40 -top-40 h-[34rem] w-[34rem] rounded-full bg-indigo-600/30 blur-[120px]"></div>
            <div class="absolute -right-32 top-20 h-[28rem] w-[28rem] rounded-full bg-sky-500/20 blur-[120px]"></div>
            <div class="absolute bottom-[-12rem] left-1/3 h-[30rem] w-[30rem] rounded-full bg-fuchsia-600/20 blur-[130px]"></div>
        </div>
        @include('partials.marketing-nav')
        <main>
            {{ $slot }}
        </main>
        @include('partials.marketing-footer')
        @stack('scripts')
    </body>
</html>
