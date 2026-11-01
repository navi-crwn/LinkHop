@props(['values' => []])
{{-- A seven-day line for one link: no script, the shape is the data. --}}
@php
    $values = array_values($values);
    $n = max(2, count($values));
    $max = max(1, ...($values ?: [0]));
    $points = collect($values)->map(fn ($v, $i) => round($i * 100 / ($n - 1), 1).','.round(27 - ($v / $max) * 24, 1))->implode(' ');
@endphp
<svg viewBox="0 0 100 30" preserveAspectRatio="none" {{ $attributes->merge(['class' => 'h-7 w-24 text-indigo-500 dark:text-indigo-300']) }} role="img" aria-label="{{ __('Clicks per day, last seven days') }}">
    <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
