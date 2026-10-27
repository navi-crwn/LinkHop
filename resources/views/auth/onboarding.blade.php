<x-guest-layout>
    <section class="mx-auto max-w-3xl px-4 py-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Welcome to HEL.ink</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Get started with your link shortening journey</p>
        </div>
        @if(session('success'))
        <div class="mt-6 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm font-medium text-green-800 dark:text-green-200">
                    {{ session('success') }}
                </p>
            </div>
        </div>
        @endif
        @if(auth()->user()->google_id && session('google_user_needs_password'))
        <div class="mt-6 rounded-lg border border-blue-500/50 bg-blue-50 p-4 dark:border-blue-500/30 dark:bg-blue-900/20">
            <div class="flex items-start gap-2">
                <div class="shrink-0 text-xl">🔑</div>
                <div class="flex-1">
                    <h2 class="text-base font-bold text-blue-900 dark:text-blue-100">Create Your Password (Required)</h2>
                    <p class="mt-1 text-xs text-blue-800 dark:text-blue-200">
                        You logged in with Google. Please create a password to secure your account and enable login with email.
                    </p>
                    <form method="POST" action="{{ route('onboarding.set-password') }}" class="mt-4 space-y-3">
                        @csrf
                        <div x-data="{ showPass: false }">
                            <label class="block text-xs font-medium text-blue-900 dark:text-blue-100 mb-1">Password <span class="text-red-600">*</span></label>
                            <div class="relative">
                                <input 
                                    :type="showPass ? 'text' : 'password'"
                                    name="password" 
                                    required
                                    class="w-full rounded-lg border border-blue-200 dark:border-blue-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm pr-10"
                                    placeholder="Enter password (min. 8 characters)"
                                />
                                <button 
                                    type="button"
                                    @click="showPass = !showPass"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-blue-600 dark:text-blue-400"
                                >
                                    <span x-show="!showPass">Show</span>
                                    <span x-show="showPass" x-cloak>Hide</span>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div x-data="{ showConf: false }">
                            <label class="block text-xs font-medium text-blue-900 dark:text-blue-100 mb-1">Confirm Password <span class="text-red-600">*</span></label>
                            <div class="relative">
                                <input 
                                    :type="showConf ? 'text' : 'password'"
                                    name="password_confirmation" 
                                    required
                                    class="w-full rounded-lg border border-blue-200 dark:border-blue-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm pr-10"
                                    placeholder="Confirm password"
                                />
                                <button 
                                    type="button"
                                    @click="showConf = !showConf"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-blue-600 dark:text-blue-400"
                                >
                                    <span x-show="!showConf">Show</span>
                                    <span x-show="showConf" x-cloak>Hide</span>
                                </button>
                            </div>
                        </div>
                        <button 
                            type="submit"
                            class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 flex items-center justify-center gap-2"
                        >
                            <span>Save and Continue to Dashboard</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endif
        <div class="mt-8">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Quick Start Guide</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-lg dark:bg-blue-900/30">
                            🔗
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Create short links</h3>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                        Shorten URLs with custom slugs, passwords, and tags.
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 text-lg dark:bg-purple-900/30">
                            📁
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Organize with folders</h3>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                        Group related links for easy management.
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-lg dark:bg-green-900/30">
                            📊
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Track analytics</h3>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                        See clicks, locations, and device breakdowns.
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 text-lg dark:bg-orange-900/30">
                            ⚙️
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Manage settings</h3>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                        Configure branding and manage your account.
                    </p>
                </div>
            </div>
        </div>
        <div class="mt-8 flex flex-col items-center justify-center gap-2 sm:flex-row">
            @if(auth()->user()->google_id && session('google_user_needs_password'))
                <p class="text-sm text-slate-600 dark:text-slate-400">Please create your password to continue</p>
            @elseif($ssoApp = \App\Http\Controllers\AppSsoController::cameFrom(request()))
                <a href="{{ \App\Http\Controllers\AppSsoController::continueUrl($ssoApp) }}" class="w-full sm:w-auto inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-blue-500">
                    Continue to {{ $ssoApp->name }}
                </a>
                <a href="{{ route('dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    Go to HEL.ink dashboard
                </a>
            @else
                <a href="{{ route('dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-blue-500">
                    Go to Dashboard
                </a>
                @if(!auth()->user()->google_id)
                    <form method="POST" action="{{ route('onboarding.skip') }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                            Skip
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </section>
</x-guest-layout>
