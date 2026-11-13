{{-- What search engines and link previews read on a public page (App\Support\Seo, family rule KONTRAK-SEO-BAHASA):
     an indexable page names its canonical address (APP_URL + path, ?lang= only when one of the eight non-English languages was asked for), og:url, the nine
     language addresses and x-default; a page that is not (an error, a missing link) says noindex and names none.
     Needs $title and $description; $index defaults to true. --}}
@php($index = $index ?? true)
<meta name="description" content="{{ $description }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:image" content="{{ route('brand.logo.dark') }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="LinkHop">
<meta name="twitter:card" content="summary">
@if ($index)
    @php($canonical = \App\Support\Seo::canonical(request()))
    <meta property="og:url" content="{{ $canonical }}">
    <link rel="canonical" href="{{ $canonical }}">
    @foreach (\App\Support\Seo::alternates(request()->path()) as $code => $href)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
    @endforeach
@else
    <meta name="robots" content="noindex">
@endif
