<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png" sizes="32x32">
    <title>{{ $reason === 'scheduled' ? __('Not live yet') : __('This link has ended') }} - {{ config('app.name', 'LinkHop') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <main class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-xl rounded-2xl bg-white p-8 text-center shadow-xl md:p-12 dark:bg-gray-800">
            <p class="font-mono text-sm text-gray-500 dark:text-gray-400" dir="ltr">{{ public_host() }}/l/{{ $link->slug }}</p>
            <h1 class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $reason === 'scheduled' ? __('Not live yet') : __('This link has ended') }}</h1>
            <p class="mt-3 text-gray-600 dark:text-gray-400">
                @if ($reason === 'scheduled')
                    {{ __('The owner has set this link to start later. Try again after :time.', ['time' => $link->starts_at->utc()->format('M j, Y H:i').' UTC']) }}
                @elseif ($reason === 'limit')
                    {{ __('This link was limited to a number of visits and all of them have been used.') }}
                @else
                    {{ __('This link was set to stop working on :time.', ['time' => $link->expires_at?->utc()->format('M j, Y H:i').' UTC']) }}
                @endif
            </p>
            <a href="{{ route('home') }}" class="mt-8 inline-block whitespace-nowrap rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white dark:bg-white dark:text-slate-900">{{ __('Back to LinkHop') }}</a>
        </div>
    </main>
</body>
</html>
