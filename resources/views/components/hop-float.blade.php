{{--
| Floating HOP app tiles behind the auth card: six 3D-looking tiles (one per app)
| drift and bounce off the viewport edges like the DVD logo, changing colour on every
| bounce. Pure decoration: aria-hidden, no pointer events, still under reduced motion.
--}}
@php
    $apps = [
        ['slug' => 'linkhop',   'name' => 'LinkHop',   'theme' => 0],
        ['slug' => 'pixelhop',  'name' => 'PixelHop',  'theme' => 2],
        ['slug' => 'notehop',   'name' => 'NoteHop',   'theme' => 1],
        ['slug' => 'biohop',    'name' => 'BioHop',    'theme' => 4],
        ['slug' => 'remotehop', 'name' => 'RemoteHop', 'theme' => 3],
        ['slug' => 'imperium',  'name' => 'Imperium',  'theme' => 5],
    ];
@endphp
<div class="hop-float" data-hop-float aria-hidden="true">
    @foreach ($apps as $i => $app)
        <div class="hop-sprite hop-theme-{{ $app['theme'] }}" data-sprite="{{ $app['slug'] }}" data-seed="{{ $i }}" style="--i: {{ $i }}">
            <div class="hop-tile">
                <span class="hop-face">
                    @switch($app['slug'])
                        @case('linkhop')
                            <img src="{{ route('brand.logo') }}" alt="" draggable="false">
                            @break
                        @case('pixelhop')
                            {{-- PixelHop's pixel mark, white --}}
                            <svg viewBox="0 0 32 32" shape-rendering="crispEdges" fill="#fff"><path d="M4 0h4v4H4zM24 0h4v4h-4zM4 4h4v4H4zM24 4h4v4h-4zM4 8h8v4H4zM20 8h8v4h-8zM4 12h24v4H4zM0 16h8v4H0zM12 16h8v4h-8zM24 16h8v4h-8zM0 20h32v4H0zM4 24h24v4H4zM8 28h16v4H8z"/></svg>
                            @break
                        @case('notehop')
                            {{-- NoteHop's pixel mark, white --}}
                            <svg viewBox="0 0 32 32" shape-rendering="crispEdges" fill="#fff"><path d="M8 0h4v4H8zM20 0h4v4h-4zM8 4h4v4H8zM20 4h4v4h-4zM4 8h16v4H4zM4 12h24v4H4zM4 16h4v4H4zM20 16h8v4h-8zM4 20h24v4H4zM4 24h4v4H4zM16 24h12v4H16zM4 28h4v4H4zM12 28h16v4H12z"/></svg>
                            @break
                        @case('biohop')
                            {{-- a leaf: the bio page that grows --}}
                            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c0-8 5-13 14-14-1 9-6 14-14 14Z"/><path d="M5 19c3-4 6-7 10-9"/></svg>
                            @break
                        @case('remotehop')
                            {{-- signal arcs: the remote that reaches --}}
                            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.9" stroke-linecap="round"><path d="M4.5 9.5a11 11 0 0 1 15 0"/><path d="M7.5 13a7 7 0 0 1 9 0"/><path d="M10.5 16.3a3 3 0 0 1 3 0"/><circle cx="12" cy="19.5" r="1" fill="#fff" stroke="none"/></svg>
                            @break
                        @case('imperium')
                            {{-- the crown: HOP control center --}}
                            <svg viewBox="0 0 24 24" fill="#fff"><path d="M3 8.5 7.2 12l4.8-6 4.8 6L21 8.5 19.4 18H4.6L3 8.5Zm2.4 11h13.2v1.6H5.4V19.5Z"/></svg>
                            @break
                    @endswitch
                </span>
            </div>
        </div>
    @endforeach
</div>
