<x-app-layout>
    <x-slot name="pageTitle">LinkHop - {{ $tag->name }}</x-slot>
    @include('partials.groups.manage', ['kind' => 'tag', 'group' => $tag])
</x-app-layout>
