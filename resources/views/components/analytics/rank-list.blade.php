@props([
    // [['label' => string, 'value' => int, 'flag' => ?string (ISO-2), 'href' => ?string, 'sub' => ?string], ...]
    'items' => [],
    'empty' => 'No data yet.',
    'limit' => 6,
    'color' => 'bg-indigo-500/70',
])
@php
    $items = collect($items)->values();
    $max = max(1, (int) $items->max('value'));
    $sum = max(1, (int) $items->sum('value'));
@endphp
@if ($items->isEmpty())
    <p {{ $attributes->merge(['class' => 'py-6 text-center text-sm text-muted']) }}>{{ $empty }}</p>
@else
    <ul {{ $attributes->merge(['class' => 'space-y-1.5']) }} x-data="{ all: false }">
        @foreach ($items as $i => $item)
            <li class="relative overflow-hidden rounded-lg" @if ($i >= $limit) x-show="all" x-cloak @endif>
                <span class="absolute inset-y-0 left-0 rounded-lg {{ $color }} opacity-[0.18]" style="width: {{ round($item['value'] / $max * 100, 1) }}%"></span>
                <div class="relative flex items-center gap-2 px-2.5 py-1.5 text-[13px]">
                    @if (! empty($item['flag']))
                        <span class="shrink-0 [&_img]:rounded-sm">{!! country_flag($item['flag']) !!}</span>
                    @endif
                    <span class="min-w-0 flex-1 truncate text-ink" title="{{ $item['label'] }}">
                        @if (! empty($item['href']))
                            <a href="{{ $item['href'] }}" class="hover:underline">{{ $item['label'] }}</a>
                        @else
                            {{ $item['label'] }}
                        @endif
                        @if (! empty($item['sub']))
                            <span class="block truncate text-[11px] text-muted">{{ $item['sub'] }}</span>
                        @endif
                    </span>
                    <span class="shrink-0 tabular-nums font-semibold text-ink">{{ number_format($item['value']) }}</span>
                    <span class="w-10 shrink-0 text-right tabular-nums text-[11px] text-muted">{{ round($item['value'] / $sum * 100) }}%</span>
                </div>
            </li>
        @endforeach
        @if ($items->count() > $limit)
            <li>
                <button type="button" class="w-full rounded-lg py-1 text-center text-xs font-medium text-indigo-600 hover:bg-indigo-500/5 dark:text-indigo-300" @click="all = !all" x-text="all ? 'Show less' : 'Show all ({{ $items->count() }})'"></button>
            </li>
        @endif
    </ul>
@endif
