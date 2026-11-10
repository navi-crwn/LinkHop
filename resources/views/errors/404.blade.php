{{-- Any address this app does not know (a deleted folder, a mistyped dashboard path). A missing short link has its
     own page (errors.link-missing), so this one does not talk about short links. Family look: the marketing shell,
     the standard buttons, light or dark with the visitor's theme. --}}
<x-marketing-layout :title="__('Page not found').' · LinkHop'" :themed="true">
    <div class="mx-auto max-w-xl px-4 py-20 text-center sm:px-6 sm:py-28">
        <p class="font-mono text-5xl font-semibold text-slate-300 dark:text-white/25" aria-hidden="true">404</p>
        <h1 class="mt-4 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl dark:text-white">{{ __('Page not found') }}</h1>
        <p class="mt-4 text-lg text-slate-600 dark:text-white/70">{{ __('This page does not exist or has moved. Check the address, or go back to where you were.') }}</p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary btn-lg">{{ __('Go to dashboard') }}</a>
                <a href="{{ route('links.index') }}" class="btn-outline btn-lg">{{ __('My links') }}</a>
            @else
                <a href="{{ url('/') }}" class="btn-primary btn-lg">{{ __('Back to homepage') }}</a>
            @endauth
        </div>
    </div>
</x-marketing-layout>
