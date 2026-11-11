@props(['link', 'card' => false])
@php
    use App\Support\WhatsAppRotator;
    $active = WhatsAppRotator::active($link);
@endphp
@if ($active)
    @php
        $numbers = array_values((array) $link->wa_numbers);
        $counts = WhatsAppRotator::clicks($link);
        $weighted = $link->wa_mode === 'weighted';
        $weights = array_sum(array_column($numbers, 'weight'));
        $clicked = array_sum(array_intersect_key($counts, array_flip(array_column($numbers, 'id'))));
    @endphp
    <section {{ $attributes->class(['glass-card' => $card]) }} data-wa-results>
        <h2 class="section-title">{{ __('WhatsApp numbers') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ $weighted ? __('Visits are shared between the numbers by weight.') : __('Visits take the numbers in turn.') }}</p>
        <ul class="mt-3 space-y-1.5">
            @foreach ($numbers as $i => $n)
                @php
                    $count = $counts[$n['id']] ?? 0;
                    $planned = $weighted ? ($weights > 0 ? round($n['weight'] / $weights * 100) : 0) : round(100 / count($numbers));
                    $actual = $clicked > 0 ? round($count / $clicked * 100) : 0;
                @endphp
                <li class="relative overflow-hidden rounded-lg">
                    <span class="absolute inset-y-0 left-0 rounded-lg bg-emerald-500/70 opacity-[0.18]" style="width: {{ $actual }}%"></span>
                    <div class="relative flex flex-wrap items-center gap-x-2 gap-y-0.5 px-2.5 py-1.5 text-[13px]">
                        <span class="chip-blue shrink-0">{{ $i + 1 }}</span>
                        <span class="min-w-0 flex-1 basis-40 truncate font-mono text-ink" dir="ltr">+{{ $n['number'] }}</span>
                        <span class="shrink-0 text-xs text-muted">{{ __('Share :share', ['share' => $planned.'%']) }}</span>
                        <span class="shrink-0 font-semibold tabular-nums text-ink">{{ number_format($count) }} <span class="font-normal text-muted">({{ $actual }}%)</span></span>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif
