<x-info-page title="Privacy" lead="What LinkHop keeps, and what it does not. The privacy policy for every HOP app is at hel.ink/privacy.">
    <h2>{{ __('When someone opens a short link') }}</h2>
    <p>{{ __('We record the time, the country and city, the device and browser type, and the referring site. For counting unique visitors we keep a keyed hash of the IP address, which cannot be turned back into the address. The IP address itself is not stored with the click.') }}</p>
    <h2>{{ __('When you make links') }}</h2>
    <p>{{ __('Your links, their settings, folders, tags and comments. With an account, they belong to your HOP ID; the account itself (email, password, two-factor) is kept by the hub.') }}</p>
    <h2>{{ __('What we never do') }}</h2>
    <ul>
        <li>{{ __('No ads and no third-party trackers.') }}</li>
        <li>{{ __('No selling or sharing of data.') }}</li>
    </ul>
    <h2>{{ __('Your data') }}</h2>
    <p>{!! __('Download or delete everything from your <a href=":url">HOP ID settings</a>. Deleting the account deletes your links and their stats in LinkHop too.', ['url' => hub_url('settings')]) !!}</p>
    <p>{!! __('The full policy: <a href="https://hel.ink/privacy">hel.ink/privacy</a>.') !!}</p>
</x-info-page>
