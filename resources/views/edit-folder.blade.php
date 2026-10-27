<x-app-layout>
    <x-slot name="pageTitle">LinkHop - {{ $folder->name }}</x-slot>
    @include('partials.groups.manage', ['kind' => 'folder', 'group' => $folder])
</x-app-layout>
