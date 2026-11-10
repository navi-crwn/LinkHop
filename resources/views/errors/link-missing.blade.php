{{-- The public "no such short link" page, in the HOP family's look: the marketing shell, light or dark with the
     visitor's theme, the standard buttons. --}}
<x-marketing-layout :title="(($expired ?? false) ? __('Link expired') : __('Link not found')).' · '.config('app.name', 'LinkHop')" :themed="true">
    <div class="mx-auto max-w-xl px-4 py-16 sm:px-6 sm:py-24">
        <div class="text-center">
            <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl dark:text-white">
                {{ ($expired ?? false) ? __('Link expired') : __('Link not found') }}
            </h1>
            @if ($expired ?? false)
            <p class="mt-4 text-lg text-slate-600 dark:text-white/75">
                {!! __('The short link :slug was a guest link and has expired.', ['slug' => '<span class="font-semibold text-blue-600 dark:text-blue-400">'.e($slug).'</span>']) !!}
            </p>
            <p class="mt-2 text-sm text-slate-500 dark:text-white/60">
                {{ __('Guest links are deleted after :idle days without a click, or after :max days. Ask the person who shared it for a new link.', ['idle' => config('shortener.guest_idle_days'), 'max' => config('shortener.guest_max_days')]) }}
            </p>
            @else
            <p class="mt-4 text-lg text-slate-600 dark:text-white/75">
                {!! __("The short link :slug doesn't exist or has been disabled.", ['slug' => '<span class="font-semibold text-blue-600 dark:text-blue-400">'.e($slug ?? '?').'</span>']) !!}
            </p>
            <p class="mt-2 text-sm text-slate-500 dark:text-white/60">
                {{ __('It may have been deleted, expired, or you may have mistyped the URL.') }}
            </p>
            @endif
        </div>
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white/70 p-5 text-left dark:border-white/10 dark:bg-white/5">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
                {{ __('What you can do:') }}
            </h2>
            <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-white/70">
                @foreach ([__('Double-check the URL for typos'), __('Contact the person who shared this link'), __('Create your own short links for free')] as $tip)
                    <li class="flex items-start gap-2">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ $tip }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="{{ url('/') }}" class="btn-primary btn-lg">{{ __('Go to homepage') }}</a>
            @auth
            <a href="{{ route('dashboard') }}" class="btn-outline btn-lg">{{ __('Go to dashboard') }}</a>
            @endauth
        </div>
        <p class="mt-8 border-t border-slate-200 pt-6 text-center text-sm text-slate-500 dark:border-white/10 dark:text-white/60">
            {{ __('Need help? Contact us at') }}
            {{-- <!--email_off--> keeps Cloudflare's Email Obfuscation from rewriting the address into "[email protected]". --}}
            <!--email_off--><a href="mailto:{{ config('mail.addresses.support', 'support@hel.ink') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">{{ config('mail.addresses.support', 'support@hel.ink') }}</a><!--/email_off-->
        </p>
    </div>
</x-marketing-layout>
