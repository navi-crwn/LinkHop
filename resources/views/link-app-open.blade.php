<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png" sizes="32x32">
    <title>{{ __('Opening the app') }} - {{ config('app.name', 'LinkHop') }}</title>
    {{-- Self-contained on purpose: nothing to download before the app is tried, on a phone with a slow connection. --}}
    <style>
        :root { color-scheme: light dark; --bg: #f8fafc; --card: #fff; --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --btn: #0f172a; --btn-ink: #fff; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b1020; --card: #111827; --ink: #f1f5f9; --muted: #94a3b8; --line: #1e293b; --btn: #f1f5f9; --btn-ink: #0f172a; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 20px; background: var(--bg); color: var(--ink); font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { width: min(100%, 420px); padding: 28px 24px; border: 1px solid var(--line); border-radius: 24px; background: var(--card); text-align: center; }
        .mark { width: 56px; height: 56px; margin: 0 auto 14px; }
        .host { margin: 0; font: 12px ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--muted); overflow-wrap: anywhere; }
        h1 { margin: 6px 0 6px; font-size: 24px; letter-spacing: -.02em; }
        p { margin: 0; color: var(--muted); font-size: 14px; }
        .actions { display: grid; gap: 10px; margin-top: 22px; }
        a.btn { display: block; padding: 13px 18px; border-radius: 14px; text-decoration: none; font-weight: 600; white-space: nowrap; }
        a.primary { background: var(--btn); color: var(--btn-ink); }
        a.ghost { border: 1px solid var(--line); color: var(--ink); }
        .dots { display: inline-flex; gap: 4px; margin-top: 4px; }
        .dots i { width: 6px; height: 6px; border-radius: 50%; background: var(--muted); animation: blink 1.2s infinite; }
        .dots i:nth-child(2) { animation-delay: .2s; } .dots i:nth-child(3) { animation-delay: .4s; }
        @keyframes blink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }
        @media (prefers-reduced-motion: reduce) { .dots i { animation: none; opacity: .6; } }
    </style>
</head>
<body>
    <main>
        <img class="mark" src="{{ route('brand.favicon') }}" alt="" width="56" height="56">
        <p class="host" dir="ltr">{{ parse_url($web, PHP_URL_HOST) }}</p>
        <h1>{{ $deep['app_name'] ? __('Opening :app', ['app' => $deep['app_name']]) : __('Opening the app') }}</h1>
        <p>{{ __('If nothing happens, the app is probably not installed on this device.') }}</p>
        <div class="dots" aria-hidden="true"><i></i><i></i><i></i></div>
        <div class="actions">
            <a class="btn primary" href="{{ $deep['app'] }}" rel="noopener">{{ __('Open the app') }}</a>
            <a class="btn ghost" href="{{ $deep['fallback'] }}" rel="noopener">{{ $deep['fallback'] === $web ? __('Continue in the browser') : __('Get the app') }}</a>
        </div>
    </main>
    <script nonce="{{ Vite::cspNonce() }}">
        (function () {
            var app = @js($deep['app']), fallback = @js($deep['fallback']), left = false;
            function gone() { left = true; }
            // The app took over (the page is hidden or loses focus): do not also send the visitor to the fallback.
            document.addEventListener('visibilitychange', function () { if (document.hidden) gone(); });
            window.addEventListener('pagehide', gone);
            window.addEventListener('blur', gone);
            setTimeout(function () { window.location.href = app; }, 60);
            setTimeout(function () { if (!left) { window.location.replace(fallback); } }, 1800);
        })();
    </script>
</body>
</html>
