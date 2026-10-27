<x-app-layout>
    <x-slot name="pageTitle">LinkHop - Account</x-slot>
    <x-page-header title="Account" subtitle="Profile, password and account removal.">
        <x-slot name="actions">
            <a href="{{ route('settings') }}" class="btn-soft btn-sm">All settings</a>
        </x-slot>
    </x-page-header>

    {{-- auth-card maps the Breeze partials' gray utilities onto the theme tokens. --}}
    <div class="auth-card glass mx-auto w-full max-w-4xl divide-glass rounded-2xl">
        <div class="p-5 [&_section>div]:max-w-none [&_header+form]:max-w-xl">
            @include('profile.partials.update-profile-information-form')
        </div>
        <div class="p-5 [&_header+form]:max-w-xl">
            @include('profile.partials.update-password-form')
        </div>
        <div class="p-5">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
