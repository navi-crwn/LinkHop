<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark bg-slate-950 text-white">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Page not found') }} · LinkHop</title>
        <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png">
        <x-hop::fonts />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            body {font-family: var(--hop-font-body, system-ui, sans-serif);}
        </style>
    </head>
    {{-- Any address this app does not know (a deleted folder, a mistyped dashboard path). A missing short link has its
         own page (errors.link-missing), so this one does not talk about short links. --}}
    <body class="min-h-full bg-[#0a0a0c] text-white">
        <div class="flex min-h-screen flex-col items-center justify-center px-6 text-center">
            <div class="flex flex-col items-center">
                <img src="{{ route('brand.logo') }}" alt="{{ __('LinkHop logo') }}" class="h-16 w-16 rounded-full">
                <p class="mt-4 text-sm text-white/60">LinkHop</p>
            </div>
            <h1 class="mt-4 text-4xl font-semibold">{{ __('Page not found') }}</h1>
            <p class="mt-2 max-w-md text-white/70">{{ __('This page does not exist or has moved. Check the address, or go back to where you were.') }}</p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary btn-lg">{{ __('Go to dashboard') }}</a>
                    <a href="{{ route('links.index') }}" class="btn-outline btn-lg">{{ __('My links') }}</a>
                @else
                    <a href="{{ url('/') }}" class="btn-primary btn-lg">{{ __('Back to homepage') }}</a>
                @endauth
            </div>
        </div>
    </body>
</html>
