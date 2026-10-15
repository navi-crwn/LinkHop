@props([
    'title',
    'subtitle' => null,
])
<div class="flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 [&_a]:rounded-xl! [&_button]:rounded-xl!">
            {{ $actions }}
        </div>
    @endisset
</div>
