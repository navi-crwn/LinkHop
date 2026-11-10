@php
    // Seconds until the limit resets, from the throttle's Retry-After header (fallback: one minute).
    $retryAfter = (int) (isset($exception) && method_exists($exception, 'getHeaders') ? ($exception->getHeaders()['Retry-After'] ?? 60) : 60);
    $retryAfter = max(1, min($retryAfter, 3600));
@endphp
<x-marketing-layout :title="__('Too many requests').' · LinkHop'" :themed="true">
    <div class="mx-auto max-w-xl px-4 py-20 text-center sm:px-6 sm:py-28">
        <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl dark:text-white">{{ __('429: Too many requests') }}</h1>
        <p class="mt-4 text-lg text-slate-600 dark:text-white/70">{{ __('Too many requests or attempts came from your connection in a short time. This helps keep LinkHop stable for everyone.') }}</p>
        <p class="mt-3 text-sm text-slate-500 dark:text-white/60">
            {{ __('Please wait a moment, then refresh this page or open it again.') }}
            <span data-retry-message>{!! __('You can try again in :seconds seconds.', ['seconds' => '<strong data-retry-seconds>'.$retryAfter.'</strong>']) !!}</span>
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ request()->isMethod('get') ? url()->current() : url()->previous() }}" class="btn-primary btn-lg">{{ __('Try again') }}</a>
            <a href="{{ url()->previous() }}" class="btn-outline btn-lg">{{ __('Go back') }}</a>
        </div>
    </div>
</x-marketing-layout>
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
