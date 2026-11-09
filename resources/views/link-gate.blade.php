<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $link->isSensitive() ? __('Sensitive Content Warning') : __('Age Verification Required') }} — {{ $link->custom_title ?: 'LinkHop' }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <x-hop::fonts />
<script nonce="{{ Vite::cspNonce() }}">if (/(^|; )hop_ui=clean/.test(document.cookie)) document.documentElement.dataset.ui = 'clean';</script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--hop-font-body, 'Inter'), -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            background: radial-gradient(circle at 50% 10%, #1e1b4b 0%, #0f172a 45%, #020617 100%);
            color: #f8fafc;
        }
        .container {
            width: 100%;
            max-width: 24rem;
            margin: 0 auto;
        }
        .card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 14px;
            padding: 2rem 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            text-align: center;
        }
        .brand-header {
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--hop-font-display, var(--hop-font-body, system-ui)), sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
        }
        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }
        .badge-adult {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .badge-sensitive {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .icon-circle {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.25rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon-adult {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(185, 28, 28, 0.2));
            border: 2px solid rgba(239, 68, 68, 0.4);
            color: #ef4444;
        }
        .icon-sensitive {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(180, 83, 9, 0.2));
            border: 2px solid rgba(245, 158, 11, 0.4);
            color: #f59e0b;
        }
        h1 {
            font-family: var(--hop-font-display, var(--hop-font-body, system-ui)), sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
            line-height: 1.25;
        }
        .target-title {
            color: #94a3b8;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .description {
            color: #cbd5e1;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1.25rem;
        }
        .reason-box {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            color: #e2e8f0;
            text-align: left;
            margin-bottom: 1.25rem;
            line-height: 1.4;
        }
        .reason-box strong {
            color: #94a3b8;
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.825rem;
            color: #94a3b8;
            margin-bottom: 1.5rem;
            cursor: pointer;
            user-select: none;
        }
        .checkbox-container input {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            flex: none;
            display: inline-grid;
            place-content: center;
            width: 16px;
            height: 16px;
            border: 1px solid rgba(148, 163, 184, 0.6);
            border-radius: 6px;
            background: rgba(15, 23, 42, 0.6);
        }
        .checkbox-container input:checked { background: #6366f1; border-color: #6366f1; }
        .checkbox-container input:checked::after {
            content: '';
            width: 9px;
            height: 5px;
            border: solid #fff;
            border-width: 0 0 2px 2px;
            transform: translateY(-1px) rotate(-45deg);
        }
        .checkbox-container input:focus-visible { outline: none; box-shadow: 0 0 0 3px rgb(129 140 248 / 0.45); }
        /* The HOP control standard: 8px radius, 40px for the one action of the card (full width, the card is 24rem),
           36px for Go Back, which is as wide as its text. Hover changes the colour only. */
        .btn-continue {
            width: 100%;
            height: 40px;
            padding: 0 16px;
            border-radius: 8px;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background-color .15s ease, box-shadow .15s ease, transform .12s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 0.75rem;
            color: #ffffff;
        }
        .btn-continue:active { transform: scale(.97); }
        .btn-continue:focus-visible, .btn-back:focus-visible { outline: none; box-shadow: 0 0 0 3px rgb(129 140 248 / 0.45); }
        .btn-adult { background-color: #dc2626; }
        .btn-adult:hover { background-color: #b91c1c; }
        .btn-sensitive { background-color: #b45309; }
        .btn-sensitive:hover { background-color: #92400e; }
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 36px;
            padding: 0 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.14);
            text-decoration: none;
            transition: background-color .15s ease, color .15s ease;
        }
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        @media (pointer: coarse) { .btn-continue, .btn-back { min-height: 44px; } }
        @media (prefers-reduced-motion: reduce) {
            .btn-continue, .btn-back { transition: none; }
            .btn-continue:active { transform: none; }
        }
        .footer-note {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 1.25rem;
            line-height: 1.4;
        }
        .brand-credit {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }
        .brand-credit a {
            color: #94a3b8;
            text-decoration: none;
            font-weight: 600;
        }
        .brand-credit a:hover {
            color: #ffffff;
        }
        html[data-ui="clean"] body {background:#0a0a0c;}
        html[data-ui="clean"] .card {background:#141418; backdrop-filter:none; -webkit-backdrop-filter:none; border-color:rgba(255,255,255,0.09); box-shadow:none;}
    </style>
</head>
<body>
    <main class="container" role="main">
        <div class="card">
            <div class="brand-header">
                <a href="{{ url('/') }}" class="brand-logo" aria-label="LinkHop Homepage">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-indigo-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    <span>LinkHop</span>
                </a>
            </div>

            @if($link->isSensitive())
                <div class="icon-circle icon-sensitive" aria-hidden="true">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="badge-pill badge-sensitive">
                    {{ __('Sensitive Content Warning') }}
                </div>
                <h1>{{ __('Sensitive Content Warning') }}</h1>
                <p class="description">{{ __('The creator has marked this link as containing sensitive material.') }}</p>
            @else
                <div class="icon-circle icon-adult" aria-hidden="true">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="badge-pill badge-adult">
                    18+ {{ __('Age Verification Required') }}
                </div>
                <h1>{{ __('Age Verification Required') }}</h1>
                <p class="description">{{ __('This link contains adult content intended strictly for viewers aged 18 and older.') }}</p>
            @endif

            @if($link->custom_title)
                <div class="target-title" title="{{ $link->custom_title }}">{{ $link->custom_title }}</div>
            @endif

            @if($link->warning_reason)
                <div class="reason-box">
                    <strong>{{ __('Note from creator:') }}</strong>
                    <span>{{ $link->warning_reason }}</span>
                </div>
            @endif

            <form method="POST" action="{{ request()->fullUrl() }}" novalidate>
                @csrf
                <input type="hidden" name="gate_confirm" value="yes">
                
                <label class="checkbox-container">
                    <input type="checkbox" name="remember" value="1" id="remember-gate">
                    <span>{{ __('Remember for 24 hours on this device') }}</span>
                </label>

                <button type="submit" data-ctl-block class="btn-continue {{ $link->isSensitive() ? 'btn-sensitive' : 'btn-adult' }}">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>{{ $link->isSensitive() ? __('I understand — Continue') : __('I am 18+ — Continue') }}</span>
                </button>

                {{-- Back to the page the visitor came from; the home page only when there is none (a link opened directly). --}}
                <a href="{{ route('home') }}" class="btn-back" id="gate-back">
                    {{ __('Go Back') }}
                </a>
            </form>
            <script nonce="{{ Vite::cspNonce() }}">
                document.getElementById('gate-back').addEventListener('click', function (e) {
                    if (window.history.length > 1 && document.referrer && document.referrer !== window.location.href) {
                        e.preventDefault();
                        window.history.back();
                    }
                });
            </script>

            @if($link->isAdult())
            <p class="footer-note">
                {{ __('By continuing, you confirm that you are of legal age to view adult content in your jurisdiction.') }}
            </p>
            @endif
        </div>

        <div class="brand-credit">
            <span>Powered by</span>
            <a href="{{ url('/') }}">LinkHop</a>
        </div>
    </main>
</body>
</html>
