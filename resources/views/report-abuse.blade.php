<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark bg-slate-950 text-white">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- A form for reporting a link (often with ?slug=), not a page to find in search results. --}}
        <meta name="robots" content="noindex">
        <title>{{ __('Report abuse') }} · LinkHop</title>
        <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png">
        <x-hop::fonts />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-linear-to-br from-slate-950 via-slate-900 to-slate-950 font-sans">
        <x-session-expired-notice />
        <div class="mx-auto flex min-h-screen max-w-2xl flex-col justify-center px-6 py-12">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur-sm">
                <h1 class="text-3xl font-semibold text-white">{{ __('Report abuse') }}</h1>
                <p class="mt-2 text-sm text-white/70">{{ __('Tell us about scams, phishing, impersonation, malware, or anything else that breaks the rules. A moderator reviews every report.') }}</p>
                @if (session('status'))
                    <div class="mt-4 rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mt-4 rounded-2xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('report.store') }}" class="mt-6 space-y-4" x-data="turnstileForm(@js(config('services.turnstile.site_key')))" x-on:submit="handleSubmit($event)">
                    @csrf
                    <input type="hidden" name="slug" value="{{ old('slug', $prefill['slug'] ?? '') }}">
                    <div>
                        <label for="freport-1" class="label">{{ __('Page address') }}</label>
                        <input id="freport-1" type="url" name="url" value="{{ old('url', $prefill['url'] ?? '') }}" class="input" placeholder="https://hel.ink/..." required>
                    </div>
                    <div>
                        <label for="freport-2" class="label">{{ __('Your email') }} {{ __('(so we can follow up)') }}</label>
                        <input id="freport-2" type="email" name="email" value="{{ old('email') }}" class="input" placeholder="your@email.com" required>
                    </div>
                    <div>
                        <label for="freport-3" class="label">{{ __('What is wrong?') }}</label>
                        <textarea id="freport-3" name="reason" rows="4" class="input" required>{{ old('reason') }}</textarea>
                    </div>
                    <x-turnstile />
                    <div class="flex items-center justify-between text-xs text-white/60">
                        <span>{{ __('To prevent spam, each network can send a few reports per half hour.') }}</span>
                        <a href="{{ url('/') }}" class="text-blue-300 hover:underline">{{ __('Go to homepage') }}</a>
                    </div>
                    <button type="submit" class="btn-primary btn-lg max-sm:w-full"><span x-show="!waiting">{{ __('Send report') }}</span><span x-cloak x-show="waiting">{{ __('Verifying…') }}</span></button>
                </form>
            </div>
        </div>
    </body>
</html>
