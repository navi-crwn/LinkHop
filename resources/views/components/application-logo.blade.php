{{-- Logo.png is light-on-transparent (dark UI); Favicon.png is the dark-on-transparent mark (light UI). --}}
<img src="{{ route('brand.favicon') }}" alt="HEL.ink logo" {{ $attributes->merge(['class' => 'dark:hidden']) }}>
<img src="{{ route('brand.logo') }}" alt="HEL.ink logo" {{ $attributes->merge(['class' => 'hidden dark:block']) }}>
