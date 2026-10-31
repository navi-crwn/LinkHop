<x-info-page title="Features" lead="Everything is included for every account. No premium tier, no locked features.">
    <h2 id="links">{{ __('Short links') }}</h2>
    <ul>
        <li>{!! __('<strong>Random or custom slugs</strong> at <code>hel.ink/l/your-slug</code>.') !!}</li>
        <li>{!! __('<strong>Password and expiry date</strong> per link, when you need them.') !!}</li>
        <li>{!! __('<strong>QR codes</strong> for every link, as PNG, SVG or JPG.') !!}</li>
        <li>{!! __('<strong>No account needed</strong> to shorten. Guest links are deleted after :idle days without a click, and after :max days at most.', ['idle' => $idleDays, 'max' => $maxDays]) !!}</li>
    </ul>
    <h2 id="analytics">{{ __('Analytics') }}</h2>
    <p>{{ __('Clicks, unique visitors, countries and cities, devices, browsers and referrers, in real time, with CSV export. Visitors are counted with a keyed hash of their IP address, never the address itself.') }}</p>
    <h2 id="organize">{{ __('Organize') }}</h2>
    <p>{{ __('Folders and tags, search, bulk actions, and comments on a link.') }}</p>
    <h2 id="safety">{{ __('Safety') }}</h2>
    <ul>
        <li>{!! __('<strong>Link check</strong>: see where a short link goes before you open it, at <a href=":url">Check a link</a>.', ['url' => route('link.check')]) !!}</li>
        <li>{!! __('<strong>Abuse protection</strong>: rate limits, a domain blacklist, proxy and VPN checks, an hourly scan for phishing and scams, and a <a href=":url">report form</a>.', ['url' => route('report.create')]) !!}</li>
    </ul>
    <h2 id="api">{{ __('API and ShareX') }}</h2>
    <p>{!! __('A REST API with scoped tokens and a ready-made ShareX config. See the <a href=":url">API docs</a>.', ['url' => route('pages.docs')]) !!}</p>
    <h2 id="account">{{ __('One account') }}</h2>
    <p>{!! __('Sign in with your free <a href="https://hel.ink">HOP ID</a>, the same account as BioHop, PixelHop and NoteHop, with two-factor sign-in and a login history.') !!}</p>
    <p class="mt-8"><a class="info-cta" href="{{ route('home') }}">{{ __('Shorten a link') }}</a></p>
</x-info-page>
