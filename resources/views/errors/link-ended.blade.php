<x-marketing-layout :title="($reason === 'scheduled' ? __('Not live yet') : __('This link has ended')).' - '.config('app.name', 'LinkHop')" :themed="true" :index="false">
    <div class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6 sm:py-24">
        <p class="font-mono text-sm text-slate-500 dark:text-white/60" dir="ltr">{{ public_host() }}/l/{{ $link->slug }}</p>
        <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl dark:text-white">{{ $reason === 'scheduled' ? __('Not live yet') : __('This link has ended') }}</h1>
        <p class="mt-4 text-lg text-slate-600 dark:text-white/70">
            @if ($reason === 'scheduled')
                {{ __('The owner has set this link to start later. Try again after :time.', ['time' => $link->starts_at->utc()->format('M j, Y H:i').' UTC']) }}
            @elseif ($reason === 'limit')
                {{ __('This link was limited to a number of visits and all of them have been used.') }}
            @else
                {{ __('This link was set to stop working on :time.', ['time' => $link->expires_at?->utc()->format('M j, Y H:i').' UTC']) }}
            @endif
        </p>
        <a href="{{ route('home') }}" class="btn-primary btn-lg mt-8">{{ __('Back to LinkHop') }}</a>
    </div>
</x-marketing-layout>
