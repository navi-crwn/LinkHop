@props([
    'title',
    'subtitle' => null,
])
{{-- The subtitle is a string, or the `sub` slot when it holds markup (a count a script keeps current). --}}
<div class="flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>
        @elseif (isset($sub))
            <p class="mt-0.5 text-sm text-muted">{{ $sub }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
