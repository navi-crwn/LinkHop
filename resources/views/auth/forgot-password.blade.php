<x-guest-layout>
    <div class="mb-6">
        <h1 class="page-title">Reset your password</h1>
        <p class="mt-1 text-sm text-muted">Enter your account email and we'll send you a reset link.</p>
    </div>
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('login') }}" class="text-sm text-muted underline hover:text-ink">Back to login</a>
            <x-primary-button>Send reset link</x-primary-button>
        </div>
    </form>
</x-guest-layout>
