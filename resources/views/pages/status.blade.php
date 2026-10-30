<x-info-page title="Status">
    <p class="info-status">
        <span @class(['info-dot', 'is-ok' => $db]) aria-hidden="true"></span>
        <span><strong>{{ $db ? 'LinkHop is up.' : 'LinkHop is having trouble.' }}</strong> This page was made by the server just now.</span>
    </p>
    <dl class="info-dl">
        <dt>Short links and dashboard</dt><dd>{{ $db ? 'OK' : 'Database not answering' }}</dd>
        <dt>Background jobs</dt><dd>{{ $queue ? 'OK' : 'Waiting for the queue' }}@if ($heartbeat) <span class="text-slate-500 dark:text-white/50">(last seen {{ \Illuminate\Support\Carbon::parse($heartbeat)->diffForHumans() }})</span>@endif</dd>
    </dl>
    <p>The HOP watchdog checks every app each minute. History and the other apps are on <a href="https://hel.ink/status">hel.ink/status</a>.</p>
</x-info-page>
