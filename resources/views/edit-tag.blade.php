<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - {{ $tag->name }}</x-slot>
    @include('partials.groups.manage', ['kind' => 'tag', 'group' => $tag])
</x-app-layout>
