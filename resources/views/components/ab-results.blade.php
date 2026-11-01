@props(['link', 'card' => false])
@php
    use App\Support\AbSplit;
    $variants = AbSplit::variants($link);
@endphp
@if ($variants)
    @php
        $counts = AbSplit::clicks($link);
        $weights = array_sum(array_column($variants, 'weight'));
        $clicked = array_sum($counts);
    @endphp
    <section {{ $attributes->class(['glass-card' => $card]) }}>
        <h2 class="section-title">{{ __('Split test results') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ __('Visitors are sent to one address by weight, and the same visitor keeps seeing the same one. A matching routing rule comes first.') }}</p>
        <ul class="mt-3 space-y-1.5">
            @foreach ($variants as $i => $variant)
                @php
                    $n = $counts[$i + 1] ?? 0;
                    $planned = $weights > 0 ? round($variant['weight'] / $weights * 100) : 0;
                    $actual = $clicked > 0 ? round($n / $clicked * 100) : 0;
                @endphp
                <li class="relative overflow-hidden rounded-lg">
                    <span class="absolute inset-y-0 left-0 rounded-lg bg-indigo-500/70 opacity-[0.18]" style="width: {{ $actual }}%"></span>
                    <div class="relative flex flex-wrap items-center gap-x-2 gap-y-0.5 px-2.5 py-1.5 text-[13px]">
                        <span class="chip-blue shrink-0">{{ chr(65 + $i) }}</span>
                        <span class="min-w-0 flex-1 basis-40 truncate text-ink" title="{{ $variant['url'] }}">{{ $variant['url'] }}</span>
                        <span class="shrink-0 text-xs text-muted">{{ __('Weight :share', ['share' => $planned.'%']) }}</span>
                        <span class="shrink-0 font-semibold tabular-nums text-ink">{{ number_format($n) }} <span class="font-normal text-muted">({{ $actual }}%)</span></span>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif
