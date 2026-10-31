<x-info-page title="Status">
    <p class="info-status">
        <span @class(['info-dot', 'is-ok' => $db]) aria-hidden="true"></span>
        <span><strong>{{ $db ? __('LinkHop is up.') : __('LinkHop is having trouble.') }}</strong> {{ __('This page was made by the server just now.') }}</span>
    </p>
    <dl class="info-dl">
        <dt>{{ __('Short links and dashboard') }}</dt><dd>{{ $db ? __('OK') : __('Database not answering') }}</dd>
        <dt>{{ __('Background jobs') }}</dt><dd>{{ $queue ? __('OK') : __('Waiting for the queue') }}@if ($heartbeat) <span class="text-slate-500 dark:text-white/50">({{ __('last seen :time', ['time' => \Illuminate\Support\Carbon::parse($heartbeat)->diffForHumans()]) }})</span>@endif</dd>
    </dl>
    <p>{!! __('The HOP watchdog checks every app each minute. History and the other apps are on <a href="https://hel.ink/status">hel.ink/status</a>.') !!}</p>
</x-info-page>
