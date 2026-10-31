<x-info-page title="Contact" lead="LinkHop is open source, so most conversations happen on GitHub.">
    <ul>
        <li>{!! __('<strong>Found a bug or have an idea?</strong> <a href="https://github.com/navi-crwn/LinkHop/issues/new/choose" rel="noopener">Open an issue</a>.') !!}</li>
        <li>{!! __('<strong>Want to fix it yourself?</strong> Pull requests are welcome: <a href="https://github.com/navi-crwn/LinkHop/pulls" rel="noopener">see open PRs</a>.') !!}</li>
        <li>{!! __('<strong>Security problem?</strong> Please report it privately with a <a href="https://github.com/navi-crwn/LinkHop/security/advisories/new" rel="noopener">security advisory</a> (see <a href="https://github.com/navi-crwn/LinkHop/blob/main/SECURITY.md" rel="noopener">SECURITY.md</a>).') !!}</li>
        <li>{!! __('<strong>A harmful short link?</strong> Use the <a href=":url">report form</a>.', ['url' => route('report.create')]) !!}</li>
        <li>{!! __('<strong>Your account</strong> (sign-in, profile, deleting it) is managed at <a href=":url">HOP ID</a>.', ['url' => hub_url('settings')]) !!}</li>
    </ul>
</x-info-page>
