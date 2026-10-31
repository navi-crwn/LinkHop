<x-info-page title="Help" lead="How to do the common things. General questions are in the FAQ.">
    <h2 id="shorten">Shorten a link</h2>
    <ol>
        <li>Paste the long link on the <a href="{{ route('home') }}">home page</a>.</li>
        <li>Optional: pick your own slug, a password or an expiry date.</li>
        <li>Press <strong>Shorten</strong> and copy your link, or download its QR code.</li>
    </ol>
    <h2 id="manage">See clicks and manage links</h2>
    <p>Sign in with your HOP ID and open the <a href="{{ route('dashboard') }}">dashboard</a>: every link with its clicks, folders and tags, and the analytics page.</p>
    <h2 id="guest">Keep a guest link</h2>
    <p>Guest links are deleted after {{ $idleDays }} days without a click, and after {{ $maxDays }} days at most. Sign up for a free HOP ID to keep links and see their stats.</p>
    <h2 id="api">Use the API</h2>
    <p>Create a token at the hub (<strong>HOP ID → Settings → API</strong>), then follow the <a href="{{ route('pages.docs') }}">API docs</a>.</p>
    <h2 id="check">Check a link before opening it</h2>
    <p>Paste any hel.ink short link on <a href="{{ route('link.check') }}">Check a link</a> to see where it goes.</p>
    <h2 id="report">Report a harmful link</h2>
    <p>Use the <a href="{{ route('report.create') }}">report form</a>. Reports go to the moderation queue.</p>
    <h2 id="old">Old links</h2>
    <p>Short links moved to <code>hel.ink/l/slug</code> on 28 October 2026. The old form <code>hel.ink/slug</code> redirects there until 27 November 2026.</p>
</x-info-page>
