{{-- Video and music blocks on the public page; same URL rules as the editor preview (Hop\Bio\Support\BioEmbed). --}}
@php
    $embed = in_array($link->type, ['music', 'spotify', 'soundcloud'], true)
        ? \Hop\Bio\Support\BioEmbed::music($link->embed_url)
        : \Hop\Bio\Support\BioEmbed::video($link->embed_url);
    $frameTitle = $link->title ?: ($embed && $embed['kind'] === 'music' ? 'Music player' : 'Video player');
@endphp
@if($embed)
    <div class="w-full rounded-xl overflow-hidden {{ $embed['kind'] === 'music' ? 'p-3' : '' }}" style="background: rgba(0,0,0,0.05);">
        @if($link->title)
            <div class="{{ $embed['kind'] === 'music' ? 'mb-2' : 'px-4 py-2' }} text-sm font-medium" style="color: inherit;">{{ $link->title }}</div>
        @endif
        @if($embed['kind'] === 'video')
            <div class="aspect-video">
                <iframe src="{{ $embed['src'] }}" title="{{ $frameTitle }}" class="w-full h-full" loading="lazy" width="560" height="315"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe>
            </div>
        @elseif($embed['kind'] === 'tiktok')
            <iframe src="{{ $embed['src'] }}" title="{{ $frameTitle }}" class="mx-auto block w-full" style="max-width: 325px;" loading="lazy" width="325" height="{{ $embed['height'] }}"
                allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>
        @else
            <iframe src="{{ $embed['src'] }}" title="{{ $frameTitle }}" class="w-full rounded-lg" loading="lazy" width="560" height="{{ $embed['height'] }}"
                allow="autoplay *; clipboard-write; encrypted-media *; fullscreen *; picture-in-picture"
                @if(str_starts_with($embed['src'], 'https://embed.music.apple.com/')) sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation" @endif></iframe>
        @endif
    </div>
@endif
