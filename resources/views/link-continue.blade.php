<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png" sizes="32x32">
    <title>{{ __('Continue to :host', ['host' => $host]) }} - {{ config('app.name', 'LinkHop') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    {{-- Shown for a moment after a gate form is accepted: the Refresh header opens the link with a GET (RedirectController::continueTo). --}}
    <main class="flex min-h-dvh items-center justify-center px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-sm dark:bg-gray-800">
            <p class="font-mono text-sm text-gray-500 dark:text-gray-400" dir="ltr">{{ public_host() }}/l/{{ $link->slug }}</p>
            <a href="{{ $url }}" class="mt-6 inline-block max-w-full truncate whitespace-nowrap rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white dark:bg-white dark:text-slate-900">{{ __('Continue to :host', ['host' => $host]) }}</a>
        </div>
    </main>
</body>
</html>
