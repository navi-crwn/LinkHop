{{-- The signed-in person in the top bar of every dashboard: who you are, your HOP ID, this app's settings, sign out.
     The same menu in every app, in the same place, so the dashboards feel like one product.

     LinkHop's copy of hop/ui's account-menu (published view), with one change: the address is wrapped in
     <!--email_off-->…<!--/email_off-->, so Cloudflare's Email Obfuscation leaves it alone. Without it the menu showed
     "[email protected]" wherever the de-obfuscation script was blocked by the CSP. Keep in step with hop/ui. --}}
@props(['user', 'settingsUrl' => null, 'accountUrl' => null, 'logoutUrl' => null])
@php
    $name = (string) ($user->name ?? '');
    $initial = strtoupper(mb_substr($name !== '' ? $name : (string) ($user->email ?? 'U'), 0, 1));
@endphp
<details class="hop-switcher hop-account" data-hop-switcher>
    <summary aria-label="{{ __('Account') }}" title="{{ $name }}">
        <span class="hop-avatar" aria-hidden="true">{{ $initial }}</span>
    </summary>
    <nav class="hop-switcher-panel hop-account-panel" aria-label="{{ __('Account') }}">
        <div class="hop-account-who">
            <span class="hop-avatar" aria-hidden="true">{{ $initial }}</span>
            <span><b>{{ $name }}</b><small><!--email_off-->{{ $user->email ?? '' }}<!--/email_off--></small></span>
        </div>
        @if ($accountUrl)<a href="{{ $accountUrl }}">{{ __('Account (HOP ID)') }}</a>@endif
        @if ($settingsUrl)<a href="{{ $settingsUrl }}">{{ __('Settings') }}</a>@endif
        @if ($logoutUrl)
            <form method="POST" action="{{ $logoutUrl }}">
                @csrf
                <button type="submit" data-ctl="option">{{ __('Sign out') }}</button>
            </form>
        @endif
    </nav>
</details>
<x-hop::switcher-assets />
@once
<style nonce="{{ \Hop\Ui\Assets::nonce() }}">
.hop-account>summary{width:40px;height:40px}.hop-avatar{display:inline-grid;place-items:center;width:32px;height:32px;border-radius:10px;background:#0f172a;color:#fff;font:600 14px/1 inherit}.dark .hop-avatar{background:#f1f5f9;color:#0f172a}.hop-account-panel{width:280px}.hop-account-who{display:flex;align-items:center;gap:12px;padding:8px 10px 12px;border-bottom:1px solid #94a3b844;margin-bottom:6px}.hop-account-who b{display:block;font-size:14px}.hop-account-who small{display:block;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;opacity:.7}.hop-account-panel form button{display:flex;width:100%;align-items:center;padding:10px;border-radius:12px;font-size:14px;text-align:start;color:inherit;background:none;border:0;cursor:pointer}.hop-account-panel form button:hover{background:#ef444418;color:#dc2626}
</style>
@endonce
