<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <script nonce="{{ Vite::cspNonce() }}">try { var t = localStorage.getItem('helink-theme'); if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ route('brand.favicon') }}">
    <title>{{ __('Something went wrong') }} · {{ config('app.name') }}</title>
    {{-- Our own built CSS; the page still renders (with the small style block below) when the build is missing. --}}
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
    <style>
        body { font-family: var(--hop-font-body, system-ui, -apple-system, sans-serif); }
        /* Used when the CSS build is missing: the page stays readable, light or dark with the system theme. */
        .container-fallback { max-width: 42rem; margin: 0 auto; padding: 2rem; }
        .btn-fallback { display: inline-block; background: #3b82f6; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; text-decoration: none; }
    </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased dark:bg-[#0e1322] dark:text-slate-100">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-xl text-center">
            <p class="font-mono text-5xl font-semibold text-slate-300 dark:text-white/25" aria-hidden="true">500</p>
            <h1 class="mt-4 text-4xl font-bold tracking-tight sm:text-5xl">{{ __('Oops! Something went wrong') }}</h1>
            <p class="mt-4 text-lg text-slate-600 dark:text-white/70">{{ __("We're sorry, but we encountered an unexpected error.") }}</p>
            <p class="mt-2 text-sm text-slate-500 dark:text-white/60">{{ __('Try again in a moment. If it keeps happening, send a message to the address below.') }}</p>
            @if(config('app.debug'))
            <div class="mt-6 rounded-lg bg-slate-100 p-4 text-left dark:bg-white/10">
                <p class="font-mono text-xs">Error Code: {{ $exception->getCode() ?: 500 }}</p>
                <p class="mt-1 font-mono text-xs opacity-75">{{ $exception->getMessage() }}</p>
            </div>
            @endif
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ url('/') }}" class="btn-primary btn-lg">{{ __('Go to homepage') }}</a>
                <button type="button" id="go-back" class="btn-outline btn-lg">{{ __('Go back') }}</button>
            </div>
            <p class="mt-8 border-t border-slate-200 pt-6 text-sm text-slate-500 dark:border-white/10 dark:text-white/60">
                {{ __('Need help? Contact us at') }}
                <!--email_off--><a href="mailto:{{ config('mail.from.address') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">{{ config('mail.from.address') }}</a><!--/email_off-->
            </p>
        </div>
    </main>
    <script nonce="{{ Vite::cspNonce() }}">
        document.getElementById('go-back').addEventListener('click', () => window.history.back());
    </script>
</body>
</html>
