<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Password required') }} - {{ config('app.name') }}</title>
    <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png">
    <x-hop::fonts />
    @if($link->custom_title)
        <meta property="og:title" content="{{ $link->custom_title }}" />
    @endif
    @if($link->custom_description)
        <meta property="og:description" content="{{ $link->custom_description }}" />
    @endif
    @if($link->custom_image_url)
        <meta property="og:image" content="{{ $link->custom_image_url }}" />
    @endif
    <style>
        /* The HOP control standard on a page without the app's stylesheet: 8px controls, 40px for the one action of
           an auth card, a card no wider than 24rem. */
        *, *::before, *::after { box-sizing: border-box; }
        body {font-family: var(--hop-font-body, system-ui, sans-serif); margin:0; padding:1.5rem 1rem; background:#020617; color:#f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; min-height:100dvh;}
        .card {background:rgba(15,23,42,0.85); padding:2rem 1.5rem; border:1px solid rgba(255,255,255,0.1); border-radius:14px; width:100%; max-width:24rem; box-shadow:0 25px 50px -12px rgba(15,23,42,0.6);}
        h1 {margin:0; font-family: var(--hop-font-display, inherit); font-size:1.25rem; font-weight:600; letter-spacing:-0.01em;}
        p {margin:0.5rem 0 0; font-size:0.875rem; line-height:1.5; color:#94a3b8;}
        p span {color:#e2e8f0; overflow-wrap:anywhere;}
        label {display:block; margin:1.25rem 0 6px; font-size:13px; font-weight:500; color:#cbd5e1;}
        input[type="password"] {display:block; width:100%; height:40px; padding:0 12px; border-radius:8px; border:1px solid rgba(148,163,184,0.4); background:rgba(15,23,42,0.6); color:#f8fafc; font:inherit; font-size:14px; transition:border-color .15s ease, box-shadow .15s ease;}
        input[type="password"]:focus-visible {outline:none; border-color:#818cf8; box-shadow:0 0 0 3px rgb(129 140 248 / 0.3);}
        input[type="password"][aria-invalid="true"] {border-color:#fb7185; box-shadow:0 0 0 3px rgb(225 29 72 / 0.16);}
        button {display:inline-flex; align-items:center; justify-content:center; margin-top:1rem; width:100%; height:40px; padding:0 16px; border-radius:8px; background:#4f46e5; color:#fff; font:inherit; font-size:14px; font-weight:600; white-space:nowrap; border:1px solid transparent; cursor:pointer; transition:background-color .15s ease, box-shadow .15s ease, transform .12s ease;}
        button:hover {background:#4338ca;}
        button:active {transform:scale(.97);}
        button:focus-visible {outline:none; box-shadow:0 0 0 3px rgb(129 140 248 / 0.45);}
        .error {color:#fda4af; margin-top:6px; font-size:13px;}
        @media (max-width: 639px) { input[type="password"] {font-size:16px;} }
        @media (pointer: coarse) { input[type="password"], button {min-height:44px;} }
        @media (prefers-reduced-motion: reduce) { input[type="password"], button {transition:none;} button:active {transform:none;} }
    </style>
</head>
<body>
    <form method="POST" action="{{ request()->fullUrl() }}" class="card">
        @csrf
        <h1>{{ __('Password required') }}</h1>
        <p>{!! __('Enter the password to continue to :url.', ['url' => '<span class="text-slate-200">'.e($link->short_url).'</span>']) !!}</p>
        <label for="link-password">{{ __('Password') }}</label>
        <input id="link-password" type="password" name="password" required autofocus autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="link-password-error" @enderror>
        @error('password')
            <div class="error" id="link-password-error" role="alert">{{ $message }}</div>
        @enderror
        <button type="submit" data-ctl-block>{{ __('Continue') }}</button>
    </form>
</body>
</html>
