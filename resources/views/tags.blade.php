<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Tags</x-slot>
    @include('partials.groups.index', ['kind' => 'tag', 'groups' => $tags])
</x-app-layout>
