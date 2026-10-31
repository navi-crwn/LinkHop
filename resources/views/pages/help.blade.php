<x-info-page title="Help" lead="How to do the common things. General questions are in the FAQ.">
    <h2 id="shorten">{{ __('Shorten a link') }}</h2>
    <ol>
        <li>{!! __('Paste the long link on the <a href=":url">home page</a>.', ['url' => route('home')]) !!}</li>
        <li>{{ __('Optional: pick your own slug, a password or an expiry date.') }}</li>
        <li>{!! __('Press <strong>Shorten</strong> and copy your link, or download its QR code.') !!}</li>
    </ol>
    <h2 id="manage">{{ __('See clicks and manage links') }}</h2>
    <p>{!! __('Sign in with your HOP ID and open the <a href=":url">dashboard</a>: every link with its clicks, folders and tags, and the analytics page.', ['url' => route('dashboard')]) !!}</p>
    <h2 id="guest">{{ __('Keep a guest link') }}</h2>
    <p>{{ __('Guest links are deleted after :idle days without a click, and after :max days at most. Sign up for a free HOP ID to keep links and see their stats.', ['idle' => $idleDays, 'max' => $maxDays]) }}</p>
    <h2 id="api">{{ __('Use the API') }}</h2>
    <p>{!! __('Create a token at the hub (<strong>HOP ID → Settings → API</strong>), then follow the <a href=":url">API docs</a>.', ['url' => route('pages.docs')]) !!}</p>
    <h2 id="check">{{ __('Check a link before opening it') }}</h2>
    <p>{!! __('Paste any hel.ink short link on <a href=":url">Check a link</a> to see where it goes.', ['url' => route('link.check')]) !!}</p>
    <h2 id="report">{{ __('Report a harmful link') }}</h2>
    <p>{!! __('Use the <a href=":url">report form</a>. Reports go to the moderation queue.', ['url' => route('report.create')]) !!}</p>
    <h2 id="old">{{ __('Old links') }}</h2>
    <p>{!! __('Short links moved to <code>hel.ink/l/slug</code> on 28 October 2026. The old form <code>hel.ink/slug</code> redirects there until 27 November 2026.') !!}</p>
</x-info-page>
