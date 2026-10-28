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
        'image' => 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm4.5 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM21 15l-5-5-9 9',
        'note' => 'M6 3h9l4 4v14H6V3Zm8 0v5h5M9 12h6m-6 4h4',
        'user' => 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z',
    ];
    // Admins get the same menu plus the HOP control center (Imperium), where the
    // admin pages went. Needs HOP_CONTROL_URL; without it the item stays hidden.
    $control = $isAdmin && config('hop.control_url')
        ? [['label' => config('hop.control_name'), 'icon' => 'shield', 'href' => config('hop.control_url'), 'active' => 'admin.control', 'badge' => $abuseCount]]
        : [];
    $groups = array_filter([
        ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['label' => 'Analytics', 'icon' => 'chart', 'route' => 'analytics', 'active' => 'analytics'],
        ['label' => 'Links', 'icon' => 'link', 'active' => ['links*', 'folders*', 'tags*'], 'children' => [
            ['label' => 'My links', 'route' => 'links.index', 'active' => 'links*'],
            ['label' => 'Folders', 'route' => 'folders.index', 'active' => 'folders*'],
            ['label' => 'Tags', 'route' => 'tags.index', 'active' => 'tags*'],
        ]],
        bio_enabled() ? ['label' => 'Link in Bio', 'icon' => 'bio', 'route' => 'bio.index', 'active' => 'bio*'] : null,
        ...$control,
        ['label' => 'Settings', 'icon' => 'settings', 'route' => 'settings', 'active' => 'settings'],
        ['label' => 'HOP ID', 'icon' => 'user', 'href' => hub_url('settings'), 'active' => 'hub.settings', 'external' => true],
    ]);
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
                <a href="{{ $item['href'] ?? route($item['route']) }}" class="nav-item {{ $active ? 'nav-item-active' : '' }}">
                    <svg class="h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$item['icon']] }}"/></svg>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if (! empty($item['badge']))
                        <span class="chip-red">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                    @endif
                    @if (! empty($item['external']))
                        <svg class="h-3.5 w-3.5 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M8 7h9v9"/></svg>
                    @endif
                </a>
            </li>
        @endif
    @endforeach
    {{-- The other HOP apps (hop/ui, from config/hop.php), each opened through its own SSO. --}}
    <li class="pt-2"><p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-muted">HOP apps</p></li>
    <x-hop::nav-items />
</ul>
