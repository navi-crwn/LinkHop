<x-app-layout>
    <x-slot name="pageTitle">{{ __('Folders') }}</x-slot>
    @include('partials.groups.index', ['kind' => 'folder', 'groups' => $folders])
</x-app-layout>
