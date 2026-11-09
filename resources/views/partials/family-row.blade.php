{{-- The family row: HOP ID mark + "Part of Hop Easy Link", the same for everyone in both looks. --}}
@php($hopMark = collect(\Hop\Ui\Assets::manifest()['apps'] ?? [])->firstWhere('slug', 'hub')['icons']['mark']['url'] ?? null)
<a href="https://hel.ink" class="hop-family-row">
    @if ($hopMark)<img src="{{ $hopMark }}" alt="" width="20" height="20">@endif
    <span>{!! __('Part of :name', ['name' => '<b>Hop Easy Link</b>']) !!}</span>
</a>
