<x-info-page title="Features" lead="Everything is included for every account. No premium tier, no locked features.">
    <h2 id="links">Short links</h2>
    <ul>
        <li><strong>Random or custom slugs</strong> at <code>hel.ink/l/your-slug</code>.</li>
        <li><strong>Password and expiry date</strong> per link, when you need them.</li>
        <li><strong>QR codes</strong> for every link, as PNG, SVG or JPG.</li>
        <li><strong>No account needed</strong> to shorten. Guest links are deleted after {{ $idleDays }} days without a click, and after {{ $maxDays }} days at most.</li>
    </ul>
    <h2 id="analytics">Analytics</h2>
    <p>Clicks, unique visitors, countries and cities, devices, browsers and referrers, in real time, with CSV export. Visitors are counted with a keyed hash of their IP address, never the address itself.</p>
    <h2 id="organize">Organize</h2>
    <p>Folders and tags, search, bulk actions, and comments on a link.</p>
    <h2 id="safety">Safety</h2>
    <ul>
        <li><strong>Link check</strong>: see where a short link goes before you open it, at <a href="{{ route('link.check') }}">Check a link</a>.</li>
        <li><strong>Abuse protection</strong>: rate limits, a domain blacklist, proxy and VPN checks, an hourly scan for phishing and scams, and a <a href="{{ route('report.create') }}">report form</a>.</li>
    </ul>
    <h2 id="api">API and ShareX</h2>
    <p>A REST API with scoped tokens and a ready-made ShareX config. See the <a href="{{ route('pages.docs') }}">API docs</a>.</p>
    <h2 id="account">One account</h2>
    <p>Sign in with your free <a href="https://hel.ink">HOP ID</a>, the same account as BioHop, PixelHop and NoteHop, with two-factor sign-in and a login history.</p>
    <p class="mt-8"><a class="info-cta" href="{{ route('home') }}">Shorten a link</a></p>
</x-info-page>
