<x-app-layout>
    <x-slot name="pageTitle">{{ __('Tags') }}</x-slot>
    @include('partials.groups.index', ['kind' => 'tag', 'groups' => $tags])
</x-app-layout>
