<x-info-page title="FAQ" lead="Common questions. How-to steps are in Help.">
    <h2>{{ __('Is it really free?') }}</h2>
    <p>{{ __('Yes. There is no paid plan and no locked feature, and the code is MIT licensed.') }}</p>
    <h2>{{ __('Do I need an account?') }}</h2>
    <p>{{ __('Not to shorten a link. A free HOP ID keeps your links, shows their stats, and also signs you in to BioHop, PixelHop and NoteHop.') }}</p>
    <h2>{{ __('What does a click record?') }}</h2>
    <p>{{ __('The time, country and city, device, browser and referrer, plus a keyed hash of the IP address for counting unique visitors. Never the IP address itself.') }}</p>
    <h2>{{ __('Where is my bio page?') }}</h2>
    <p>{!! __('Bio pages are made in <a href="https://bio.hel.ink">BioHop</a>, at <code>hel.ink/b/yourname</code>, with the same HOP ID.') !!}</p>
    <h2>{{ __('Can I use my own domain?') }}</h2>
    <p>{!! __('Yes, by running your own copy. See the <a href="https://github.com/navi-crwn/LinkHop" rel="noopener">README</a>.') !!}</p>
    <h2>{{ __('How do I report a bad link?') }}</h2>
    <p>{!! __('Use the <a href=":url">report form</a>.', ['url' => route('report.create')]) !!}</p>
    <h2>{{ __('Is LinkHop working right now?') }}</h2>
    <p>{!! __('See the <a href=":url">status page</a>.', ['url' => route('pages.status')]) !!}</p>
</x-info-page>
