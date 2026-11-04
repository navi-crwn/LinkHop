@extends('layouts.app')
@section('title', __('Too many requests'))
@php
    // Seconds until the limit resets, from the throttle's Retry-After header (fallback: one minute).
    $retryAfter = (int) (isset($exception) && method_exists($exception, 'getHeaders') ? ($exception->getHeaders()['Retry-After'] ?? 60) : 60);
    $retryAfter = max(1, min($retryAfter, 3600));
@endphp
@section('content')
    <div class="mx-auto max-w-lg py-20 text-center">
        <h1 class="text-4xl font-bold text-rose-600 mb-4">{{ __('429: Too many requests') }}</h1>
        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">{{ __('Too many requests or attempts came from your connection in a short time. This helps keep LinkHop stable for everyone.') }}</p>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
            {{ __('Please wait a moment, then refresh this page or open it again.') }}
            <span data-retry-message>{!! __('You can try again in :seconds seconds.', ['seconds' => '<strong data-retry-seconds>'.$retryAfter.'</strong>']) !!}</span>
        </p>
        <div class="flex flex-wrap justify-center gap-3">
            <a href="{{ request()->isMethod('get') ? url()->current() : url()->previous() }}" class="inline-block rounded-full bg-blue-600 px-6 py-2 text-white font-semibold hover:bg-blue-500">{{ __('Try again') }}</a>
            <a href="{{ url()->previous() }}" class="inline-block rounded-full border border-gray-300 dark:border-gray-600 px-6 py-2 font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('Go back') }}</a>
        </div>
    </div>
@endsection
@push('scripts')
    <script nonce="{{ Vite::cspNonce() }}">
        (() => {
            const el = document.querySelector('[data-retry-seconds]');
            if (!el) return;
            let left = {{ $retryAfter }};
            const timer = setInterval(() => {
                left -= 1;
                if (left > 0) {
                    el.textContent = left;
                    return;
                }
                clearInterval(timer);
                document.querySelector('[data-retry-message]').textContent = @json(__('Reloading…'));
                // Only GET requests are safe to repeat automatically.
                @if (request()->isMethod('get'))
                    window.location.reload();
                @else
                    document.querySelector('[data-retry-message]').textContent = @json(__('You can try again now.'));
                @endif
            }, 1000);
        })();
    </script>
@endpush
