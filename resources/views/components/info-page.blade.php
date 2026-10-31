{{-- A dedicated page on LinkHop's own domain (features, about, help, …): the marketing
     header and footer, a back link to LinkHop's home, a title and the page body. --}}
@props(['title', 'lead' => null])
<x-marketing-layout :title="$title.' · LinkHop'" :themed="true">
    <main id="main" class="mx-auto max-w-3xl px-4 pb-6 pt-8 sm:px-6">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 transition hover:text-slate-900 dark:text-white/60 dark:hover:text-white">
            <span aria-hidden="true">&larr;</span> {{ __('Back to :app', ['app' => 'LinkHop']) }}
        </a>
        <h1 class="mt-6 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl dark:text-white">{{ __($title) }}</h1>
        @if ($lead)
            <p class="mt-4 text-lg text-slate-600 dark:text-white/70">{{ __($lead) }}</p>
        @endif
        <div class="info-prose mt-10">
            {{ $slot }}
        </div>
    </main>
</x-marketing-layout>
