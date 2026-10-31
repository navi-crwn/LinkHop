<x-info-page title="Terms" lead="The rules for short links on LinkHop. They add to the terms that cover every HOP app.">
    <h2>Not allowed</h2>
    <ul>
        <li>Links to phishing, malware, scams, or anything illegal where you or the visitor are.</li>
        <li>Hiding where a link goes in order to mislead people.</li>
        <li>Spam, or using LinkHop to dodge another service's blocks.</li>
        <li>Impersonating a person, brand or official body with a slug.</li>
    </ul>
    <h2>What we do</h2>
    <p>Links are checked when they are made and scanned every hour. A link that breaks these rules is disabled, and an account that keeps breaking them is suspended. Report a link with the <a href="{{ route('report.create') }}">report form</a>.</p>
    <h2>Guest links</h2>
    <p>Links made without an account are deleted after {{ $idleDays }} days without a click, and after {{ $maxDays }} days at most.</p>
    <h2>No warranty</h2>
    <p>LinkHop is free and provided as is. We do our best to keep links working, but cannot promise that every link stays available forever.</p>
    <p>The full terms for every HOP app are at <a href="https://hel.ink/terms">hel.ink/terms</a>.</p>
</x-info-page>
