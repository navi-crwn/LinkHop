@props(['isAdmin' => false, 'abuseCount' => 0])
@php
    $icons = [
        'home' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5Z',
        'chart' => 'M4 20V10m6 10V4m6 16v-7m4 7H2',
        'link' => 'M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1',
        'bio' => 'M8 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm1 4h6M9 11h6m-6 4h3',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-2-1.2L14.5 3h-5l-.4 2.6a7.6 7.6 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 0 0 2 1.2l.4 2.6h5l.4-2.6a7.6 7.6 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z',
        'shield' => 'M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z',
        'flag' => 'M5 21V4m0 0h11l-1.5 4L16 12H5',
        'globe' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0c2.5-2.5 3.5-5.5 3.5-9S14.5 5.5 12 3m0 18c-2.5-2.5-3.5-5.5-3.5-9S9.5 5.5 12 3M3.5 9h17m-17 6h17',
    ];
    if ($isAdmin) {
        $groups = [
            ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard'],
            ['label' => 'Analytics', 'icon' => 'chart', 'active' => ['analytics', 'admin.analytics*'], 'children' => [
                ['label' => 'Platform', 'route' => 'admin.analytics.global', 'active' => 'admin.analytics.global'],
                ['label' => 'My links', 'route' => 'analytics', 'active' => 'analytics'],
            ]],
            ['label' => 'Links', 'icon' => 'link', 'active' => ['admin.links*', 'links*', 'folders*', 'tags*'], 'children' => [
                ['label' => 'All links', 'route' => 'admin.links.index', 'active' => 'admin.links.index'],
                ['label' => 'My links', 'route' => 'admin.links.my', 'active' => 'admin.links.my'],
                ['label' => 'Folders', 'route' => 'folders.index', 'active' => 'folders*'],
                ['label' => 'Tags', 'route' => 'tags.index', 'active' => 'tags*'],
            ]],
            ['label' => 'Link in Bio', 'icon' => 'bio', 'active' => ['admin.bio*', 'bio*'], 'children' => [
                ['label' => 'All bio pages', 'route' => 'admin.bio.index', 'active' => ['admin.bio.index', 'admin.bio.show']],
                ['label' => 'Monitor', 'route' => 'admin.bio.monitor', 'active' => 'admin.bio.monitor'],
                ['label' => 'My bio pages', 'route' => 'bio.index', 'active' => 'bio*'],
            ]],
            ['label' => 'Abuse', 'icon' => 'flag', 'route' => 'admin.abuse.index', 'active' => 'admin.abuse*', 'badge' => $abuseCount],
            ['label' => 'Security', 'icon' => 'shield', 'active' => ['admin.users*', 'admin.ip-*', 'admin.domain-*', 'admin.geoip*', 'admin.proxy*'], 'children' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'active' => 'admin.users*'],
                ['label' => 'IP bans', 'route' => 'admin.ip-bans.index', 'active' => 'admin.ip-bans*'],
                ['label' => 'IP watchlist', 'route' => 'admin.ip-watchlist.index', 'active' => 'admin.ip-watchlist*'],
                ['label' => 'Domain blacklist', 'route' => 'admin.domain-blacklist.index', 'active' => 'admin.domain-blacklist*'],
                ['label' => 'GeoIP monitor', 'route' => 'admin.geoip.monitor', 'active' => 'admin.geoip*'],
                ['label' => 'Proxy monitor', 'route' => 'admin.proxy.monitor', 'active' => 'admin.proxy*'],
            ]],
            ['label' => 'Site & SEO', 'icon' => 'globe', 'route' => 'admin.seo.edit', 'active' => 'admin.seo*'],
            ['label' => 'Settings', 'icon' => 'settings', 'route' => 'settings', 'active' => 'settings'],
        ];
    } else {
        $groups = [
            ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard'],
            ['label' => 'Analytics', 'icon' => 'chart', 'route' => 'analytics', 'active' => 'analytics'],
            ['label' => 'Links', 'icon' => 'link', 'active' => ['links*', 'folders*', 'tags*'], 'children' => [
                ['label' => 'My links', 'route' => 'links.index', 'active' => 'links*'],
                ['label' => 'Folders', 'route' => 'folders.index', 'active' => 'folders*'],
                ['label' => 'Tags', 'route' => 'tags.index', 'active' => 'tags*'],
            ]],
            ['label' => 'Link in Bio', 'icon' => 'bio', 'route' => 'bio.index', 'active' => 'bio*'],
            ['label' => 'Settings', 'icon' => 'settings', 'route' => 'settings', 'active' => 'settings'],
        ];
    }
    $is = fn ($patterns) => request()->routeIs(...(array) $patterns);
@endphp
<ul class="space-y-1">
    @foreach ($groups as $item)
        @php $active = $is($item['active']); @endphp
        @if (! empty($item['children']))
            <li x-data="{ open: {{ $active ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="nav-item w-full {{ $active ? 'nav-item-active' : '' }}">
                    <svg class="h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$item['icon']] }}"/></svg>
                    <span class="flex-1 text-left">{{ $item['label'] }}</span>
                    <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
                </button>
                <ul x-show="open" x-collapse @if (! $active) x-cloak @endif class="ml-[30px] mt-1 space-y-0.5 border-l border-slate-500/15 pl-2">
                    @foreach ($item['children'] as $child)
                        <li><a href="{{ route($child['route']) }}" class="nav-sub {{ $is($child['active']) ? 'nav-sub-active' : '' }}">{{ $child['label'] }}</a></li>
                    @endforeach
                </ul>
            </li>
        @else
            <li>
                <a href="{{ route($item['route']) }}" class="nav-item {{ $active ? 'nav-item-active' : '' }}">
                    <svg class="h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$item['icon']] }}"/></svg>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if (! empty($item['badge']))
                        <span class="chip-red">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                    @endif
                </a>
            </li>
        @endif
    @endforeach
</ul>
