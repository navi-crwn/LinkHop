{{-- Logo.png is light-on-transparent (dark UI); Favicon.png is the dark-on-transparent mark (light UI). --}}
<img src="{{ route('brand.favicon') }}" alt="LinkHop logo" {{ $attributes->merge(['class' => 'dark:hidden']) }}>
<img src="{{ route('brand.logo') }}" alt="LinkHop logo" {{ $attributes->merge(['class' => 'hidden dark:block']) }}>
