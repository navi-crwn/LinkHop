<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png" sizes="32x32">
    <title>{{ __('Unverified destination') }} - {{ config('app.name', 'LinkHop') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <main class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-xl rounded-2xl bg-white p-8 text-center shadow-xl md:p-12 dark:bg-gray-800">
            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300" aria-hidden="true">
                <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <p class="font-mono text-sm text-gray-500 dark:text-gray-400" dir="ltr">{{ public_host() }}/l/{{ $link->slug }}</p>
            <h1 class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ __('Unverified destination') }}</h1>
            <p class="mt-3 text-gray-600 dark:text-gray-400">{{ __('This short link was created by a guest and points to a site LinkHop does not know yet. It has not been checked.') }}</p>
            <p class="mt-4 rounded-xl bg-gray-100 px-4 py-3 text-sm text-gray-700 dark:bg-gray-700/60 dark:text-gray-200" dir="ltr">
                <span class="text-gray-500 dark:text-gray-400">{{ __('Destination') }}:</span>
                <strong class="break-all font-mono">{{ $host }}</strong>
            </p>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">{{ __('Never enter passwords, verification codes or payment details on a page you did not expect to see.') }}</p>
            <form method="POST" action="{{ request()->fullUrl() }}" class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                @csrf
                <input type="hidden" name="unverified_confirm" value="yes">
                <button type="submit" class="inline-block whitespace-nowrap rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white dark:bg-white dark:text-slate-900">{{ __('Continue to :host', ['host' => $host]) }}</button>
                <a href="{{ route('home') }}" class="inline-block whitespace-nowrap rounded-xl border border-gray-300 px-5 py-2.5 font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">{{ __('Back to LinkHop') }}</a>
            </form>
            <p class="mt-6 text-xs text-gray-500 dark:text-gray-500">
                <a href="{{ route('report.create', ['url' => $link->short_url]) }}" class="underline underline-offset-2">{{ __('Report abuse') }}</a>
            </p>
        </div>
    </main>
</body>
</html>
