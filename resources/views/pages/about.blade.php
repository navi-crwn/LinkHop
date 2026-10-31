<x-info-page title="About LinkHop" lead="LinkHop is the free, open-source link shortener of the HOP family.">
    <h2>{{ __('Why') }}</h2>
    <p>{{ __('Long links are hard to share and you never know who clicks them. LinkHop makes them short, counts every click without tracking the people behind them, and protects visitors from harmful links.') }}</p>
    <h2>{{ __('Who runs it') }}</h2>
    <p>{!! __('LinkHop is run by one person on one server, with no ads and no trackers. The code is MIT licensed and public on <a href="https://github.com/navi-crwn/LinkHop" rel="noopener">GitHub</a>, so you can run your own copy on your own domain.') !!}</p>
    <h2>{{ __('The HOP family') }}</h2>
    <p>{!! __('One free <a href="https://hel.ink">HOP ID</a> signs you in to every app:') !!}</p>
    <ul class="info-family">
        @foreach (\Hop\Ui\Assets::family('linkhop') as $app)
            @continue($app['current'])
            <li><a href="{{ $app['url'] }}"><img src="{{ $app['icons']['mark']['url'] }}" alt="" width="40" height="40" loading="lazy"><span><strong>{{ $app['name'] }}</strong><small>{{ $app['tagline'] }}</small></span></a></li>
        @endforeach
    </ul>
    <p>{!! __('Questions? See the <a href=":faq">FAQ</a> or <a href=":contact">get in touch</a>.', ['faq' => route('pages.faq'), 'contact' => route('pages.contact')]) !!}</p>
</x-info-page>
