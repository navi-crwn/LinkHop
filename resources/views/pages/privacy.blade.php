<x-info-page title="Privacy" lead="What LinkHop keeps, and what it does not. The privacy policy for every HOP app is at hel.ink/privacy.">
    <div class="mb-6 rounded-2xl border border-sky-500/30 bg-sky-50/50 p-4 text-sm text-sky-900 dark:border-sky-500/20 dark:bg-sky-950/20 dark:text-sky-200">
        <p class="font-semibold">{{ __('Master HOP Privacy Policy') }}</p>
        <p class="mt-1">{!! __('This policy explains LinkHop-specific data processing. It operates alongside the Master HOP Privacy Policy at <a href="https://hel.ink/privacy" class="underline font-medium">hel.ink/privacy</a>. Both documents govern your data protection rights across the HOP ecosystem.') !!}</p>
    </div>

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
    <p>{!! __('The full policy: <a href="https://hel.ink/privacy">hel.ink/privacy</a>. Questions or deletion requests: <!--email_off--><a href="mailto:support@hel.ink">support@hel.ink</a><!--/email_off-->.') !!}</p>
</x-info-page>
