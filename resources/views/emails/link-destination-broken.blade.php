<x-mail::message>
# {{ __('Destination not responding') }}

{{ __('The address behind your short link :link has not answered in several checks in a row.', ['link' => $link->short_url]) }}

**{{ __('Goes to') }}:** {{ \Illuminate\Support\Str::limit(\App\Support\EncryptedNoteLink::withoutFragment($link->target_url), 120) }}
@if ($status)

**{{ __('Last answer') }}:** HTTP {{ $status }}
@endif

{{ __('Check that the page still exists, or change the destination so visitors do not land on an error.') }}

<x-mail::button :url="route('links.edit', $link)">
{{ __('Edit link') }}
</x-mail::button>

<small>{{ __('You get this email once each time a destination stops responding. You can turn the check off in Settings, under Notifications.') }}</small>
</x-mail::message>
