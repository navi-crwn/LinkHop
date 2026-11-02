<details class="hop-switcher hop-langmenu" data-hop-switcher>
    <summary aria-label="{{ __('Language') }}" title="{{ __('Language') }}"><span class="text-xs font-semibold">{{ \Hop\Ui\Locales::short(\Hop\Ui\Locales::normalize(app()->getLocale())) }}</span></summary>
    <nav class="hop-switcher-panel" aria-label="{{ __('Language') }}">
        @foreach (\Hop\Ui\Locales::ALL as $code => $locale)
            <a href="{{ request()->fullUrlWithQuery(['lang'=>$code]) }}" lang="{{ $code }}" hreflang="{{ $code }}" @if(\Hop\Ui\Locales::normalize(app()->getLocale())===$code) aria-current="true" @endif><span>{{ $locale['name'] }}</span><small>{{ $locale['short'] }}</small></a>
        @endforeach
    </nav>
</details>
<x-hop::switcher-assets />
