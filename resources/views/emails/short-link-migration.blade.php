<x-mail::message>
# {{ $reminder ? 'Reminder: your' : 'Your' }} short links moved to {{ $host }}/l/…

Hi {{ $user->name ?: 'there' }},

{{ config('app.name') }} is becoming one of several HOP apps on {{ $host }}, so short links now have their own path: **{{ $host }}/l/slug** instead of {{ $host }}/slug. Nothing was deleted: your links, their slugs, clicks and statistics are unchanged.

@if ($reminder)
**The old form stops on {{ $until }}.** Until then {{ $host }}/slug still redirects to the new address; after that only the new address opens your link.
@else
**Until {{ $until }}** the old form still works and redirects to the new address. **From {{ $until }}** the old form stops and only the new address opens your link.
@endif

Please replace the links you have published elsewhere with their new form:

<x-mail::table>
| Short link | Goes to |
|:-----------|:--------|
@foreach ($listed as $link)
| [{{ str_replace(['https://', 'http://'], '', $link->short_url) }}]({{ $link->short_url }}) | {{ \Illuminate\Support\Str::limit($link->target_url, 60) }} |
@endforeach
</x-mail::table>
@if ($more > 0)

…and {{ $more }} more, all listed in the dashboard.
@endif

<x-mail::button :url="route('links.index')">
Open my links
</x-mail::button>

QR codes made before this change point at the old form, so download them again where they are printed. Bio pages are not affected: they stay at {{ $host }}/b/name.

This notice is sent under section 11A of the Terms of Service: the service is free, changes like this one are announced on the dashboard beforehand and must be followed.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
