{{-- The LinkHop logo (the pixel head, no text) plus the name as real text in the app's own typeface (hop-brand.css). --}}
@once
<link rel="stylesheet" href="{{ asset('hop-brand/hop-brand.css') }}">
@endonce
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <img src="{{ route('brand.asset', 'linkhop-mark.svg') }}" alt="" class="aspect-square h-full max-h-11 w-auto">
    <span class="hop-name hop-name-linkhop text-2xl leading-none">LinkHop</span>
</span>
