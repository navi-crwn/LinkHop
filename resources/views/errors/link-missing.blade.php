<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark bg-slate-950 text-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ route('brand.favicon') }}">
    <title>{{ ($expired ?? false) ? __('Link expired') : __('Link not found') }} · {{ config('app.name', 'LinkHop') }}</title>
    <x-hop::fonts />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {font-family: var(--hop-font-body, system-ui, sans-serif);}
    </style>
</head>
{{-- The public "no such short link" page, in the HOP family's dress (dark backdrop, glass card, the family fonts),
     the same as the 404 and the gate pages, instead of the blue-and-white template it had. --}}
<body class="min-h-full bg-linear-to-br from-slate-950 via-slate-900 to-slate-950 text-white">
    <div class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-xl">
            <div class="rounded-3xl border border-white/10 bg-slate-900/60 p-6 shadow-2xl shadow-black/40 backdrop-blur-xl sm:p-10">
                <div class="flex flex-col items-center text-center">
                    <img src="{{ route('brand.logo') }}" alt="{{ __('LinkHop logo') }}" class="h-14 w-14 rounded-full shadow-lg">
                    <p class="mt-3 text-xs uppercase tracking-[0.4em] text-white/50">LinkHop</p>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight">
                        {{ ($expired ?? false) ? __('Link expired') : __('Link not found') }}
                    </h1>
                    @if ($expired ?? false)
                    <p class="mt-3 text-base text-white/75">
                        {!! __('The short link :slug was a guest link and has expired.', ['slug' => '<span class="font-semibold text-blue-600 dark:text-blue-400">'.e($slug).'</span>']) !!}
                    </p>
                    <p class="mt-2 text-sm text-white/55">
                        {{ __('Guest links are deleted after :idle days without a click, or after :max days. Ask the person who shared it for a new link.', ['idle' => config('shortener.guest_idle_days'), 'max' => config('shortener.guest_max_days')]) }}
                    </p>
                    @else
                    <p class="mt-3 text-base text-white/75">
                        {!! __("The short link :slug doesn't exist or has been disabled.", ['slug' => '<span class="font-semibold text-blue-600 dark:text-blue-400">'.e($slug ?? '?').'</span>']) !!}
                    </p>
                    <p class="mt-2 text-sm text-white/55">
                        {{ __('It may have been deleted, expired, or you may have mistyped the URL.') }}
                    </p>
                    @endif
                </div>
                <div class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-5 text-left">
                    <h3 class="text-sm font-semibold text-white">
                        {{ __('What you can do:') }}
                    </h3>
                    <ul class="mt-3 space-y-2 text-sm text-white/70">
                        @foreach ([__('Double-check the URL for typos'), __('Contact the person who shared this link'), __('Create your own short links for free')] as $tip)
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>{{ $tip }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ url('/') }}" class="btn-primary btn-lg">
                        {{ __('Go to homepage') }}
                    </a>
                    @auth
                    <a href="{{ route('dashboard') }}" class="btn-outline btn-lg">
                        {{ __('Go to dashboard') }}
                    </a>
                    @endauth
                </div>
                <div class="mt-8 border-t border-white/10 pt-6 text-center">
                    <p class="text-sm text-white/60">
                        {{ __('Need help? Contact us at') }}
                        {{-- <!--email_off--> keeps Cloudflare's Email Obfuscation from rewriting the address into "[email protected]". --}}
                        <!--email_off--><a href="mailto:{{ config('mail.addresses.support', 'support@hel.ink') }}" class="font-medium text-blue-400 hover:underline">{{ config('mail.addresses.support', 'support@hel.ink') }}</a><!--/email_off-->
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
