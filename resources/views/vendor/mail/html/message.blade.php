<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
<img src="{{ config('app.url') }}/brand/logo.png" alt="" width="40" height="40" style="display:inline-block;vertical-align:middle;margin-right:10px"><span style="display:inline-block;vertical-align:middle;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.02em">{{ config('app.name') }}</span><span style="display:inline-block;vertical-align:middle;margin-left:10px;padding:2px 8px;border-radius:999px;background:rgba(255,255,255,.14);color:#dbe4ff;font-size:11px;font-weight:600;letter-spacing:.12em">HOP ID</span>
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
One HOP ID for {{ collect([config('app.name')])->merge(collect(\App\Http\Controllers\AppSsoController::apps())->pluck('name'))->implode(' · ') }}.<br>© {{ date('Y') }} {{ config('app.name') }} · [hel.ink]({{ config('app.url') }})
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
