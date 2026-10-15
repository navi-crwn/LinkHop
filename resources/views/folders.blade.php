<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Folders</x-slot>
    @include('partials.groups.index', ['kind' => 'folder', 'groups' => $folders])
</x-app-layout>
