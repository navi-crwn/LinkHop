<x-guest-layout>
    <div class="mb-6">
        <h1 class="page-title">Set a new password</h1>
        <p class="mt-1 text-sm text-muted">For your HOP ID: the same password then works on {{ config('app.name') }} and every HOP app.</p>
    </div>
    @php $lockedEmail = (string) $request->query('email', ''); @endphp
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            {{-- The link is for one address only (the token is bound to it), so the field is shown but not editable. --}}
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $lockedEmail)" required autocomplete="username" :readonly="$lockedEmail !== ''" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="password" :value="__('New password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autofocus autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm new password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>
        <x-primary-button class="mt-6 w-full py-3 text-base">{{ __('Save new password') }}</x-primary-button>
    </form>
</x-guest-layout>
