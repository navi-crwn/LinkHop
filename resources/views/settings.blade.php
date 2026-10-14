<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Settings</x-slot>
    @php
        $settingsTabs = [
            'account' => ['Account', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
            '2fa' => ['Two-factor', 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
            'backup' => ['Backup & export', 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'],
            'notifications' => ['Notifications', 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
            'activity' => ['Activity log', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
            'login' => ['Login history', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            'api' => ['API tokens', 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4'],
        ];
    @endphp

    <x-page-header title="Settings" subtitle="Your account, security and data." />

    <div class="grid gap-4 lg:grid-cols-[13rem_minmax(0,1fr)]" x-data="{
        tab: '{{ session('active_tab', 'account') }}',
        init() {
            const hashTab = window.location.hash.substring(1);
            if (@js(array_keys($settingsTabs)).includes(hashTab)) {
                this.tab = hashTab;
            }
            this.$watch('tab', value => history.replaceState(null, '', '#' + value));
        }
    }" @tab-changed.window="tab = $event.detail">
        <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 lg:sticky lg:top-24 lg:mx-0 lg:flex-col lg:self-start lg:overflow-visible lg:px-0" aria-label="Settings sections">
            @foreach ($settingsTabs as $key => [$label, $icon])
                <button type="button" @click="tab = '{{ $key }}'" class="nav-item shrink-0 whitespace-nowrap" :class="tab === '{{ $key }}' && 'nav-item-active'">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    {{ $label }}
                </button>
            @endforeach
        </nav>
        <div class="min-w-0 space-y-4">
            <x-flash />
            <div class="glass rounded-2xl">
                <div x-show="tab === 'account'" class="divide-glass">
                    {{-- Each setting is a row: what it is on the left, the fields on the right. --}}
                    <section class="grid gap-4 p-5 md:grid-cols-3 md:gap-8">
                        <div>
                            <h3 class="section-title">Profile</h3>
                            <p class="mt-1 text-xs text-muted">Your name and the email you sign in with. Changing the email asks you to verify it again.</p>
                        </div>
                        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4 md:col-span-2">
                            @csrf
                            @method('PATCH')
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="fsettin-1" class="label">Name</label>
                                    <input id="fsettin-1" type="text" name="name" value="{{ old('name', auth()->user()->name) }}" class="input" required autocomplete="name">
                                </div>
                                <div>
                                    <label for="fsettin-2" class="label">Email</label>
                                    <input id="fsettin-2" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" class="input" required autocomplete="email">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary btn-sm">Save profile</button>
                            </div>
                        </form>
                    </section>
                    <section class="grid gap-4 p-5 md:grid-cols-3 md:gap-8">
                        <div>
                            <h3 class="section-title">Password</h3>
                            <p class="mt-1 text-xs text-muted">Use a long, random password you don't use anywhere else.</p>
                        </div>
                        <form method="POST" action="{{ route('password.update') }}" class="space-y-4 md:col-span-2">
                            @csrf
                            @method('PUT')
                            @if ($errors->updatePassword->any())
                                <ul class="glass-inset list-disc space-y-0.5 border-rose-400/40 py-2.5 pl-8 pr-3 text-sm text-rose-700 dark:text-rose-300">
                                    @foreach ($errors->updatePassword->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <div>
                                <label for="fsettin-3" class="label">Current password</label>
                                <input id="fsettin-3" type="password" name="current_password" class="input sm:max-w-[calc(50%-0.5rem)]" required autocomplete="current-password">
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="fsettin-4" class="label">New password</label>
                                    <input id="fsettin-4" type="password" name="password" class="input" required autocomplete="new-password">
                                </div>
                                <div>
                                    <label for="fsettin-5" class="label">Confirm new password</label>
                                    <input id="fsettin-5" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary btn-sm">Update password</button>
                            </div>
                        </form>
                    </section>
                    @unless(auth()->user()->isSuperAdmin())
                        <section class="grid gap-4 p-5 md:grid-cols-3 md:gap-8" x-data="{
                            showModal: false,
                            password: '',
                            countdown: 10,
                            canDelete: false,
                            startCountdown() {
                                this.countdown = 10;
                                this.canDelete = false;
                                const interval = setInterval(() => {
                                    this.countdown--;
                                    if (this.countdown <= 0) {
                                        this.canDelete = true;
                                        clearInterval(interval);
                                    }
                                }, 1000);
                            }
                        }">
                            <div>
                                <h3 class="section-title text-rose-600 dark:text-rose-400">Delete account</h3>
                                <p class="mt-1 text-xs text-muted">Removes your links, bio pages, folders, tags and analytics for good.</p>
                            </div>
                            <div class="flex items-start md:col-span-2 md:justify-end">
                                <button type="button" @click="showModal = true; $nextTick(() => startCountdown())" class="btn-danger btn-sm">Delete my account</button>
                            </div>
                            <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" @click.self="showModal = false; password = ''">
                                <div class="glass-strong w-full max-w-md rounded-2xl p-5">
                                    <h3 class="text-base font-semibold text-rose-600 dark:text-rose-400">Delete account</h3>
                                    <p class="mt-1 text-sm text-muted">This is <strong class="text-ink">permanent</strong>. All your links, folders, tags and analytics are deleted.</p>
                                    <form method="POST" action="{{ route('profile.destroy') }}" class="mt-4 space-y-3">
                                        @csrf
                                        @method('DELETE')
                                        <div>
                                            <label for="fsettin-6" class="label">Confirm your password</label>
                                            <input id="fsettin-6" type="password" name="password" x-model="password" class="input" required autocomplete="current-password">
                                        </div>
                                        <p class="text-center text-xs text-muted">
                                            <span x-show="!canDelete">You can confirm in <span x-text="countdown" class="font-semibold text-ink"></span> s</span>
                                            <span x-show="canDelete">You can now delete your account.</span>
                                        </p>
                                        <div class="flex gap-2">
                                            <button type="button" @click="showModal = false; password = ''" class="btn-soft flex-1">Cancel</button>
                                            <button type="submit" :disabled="!canDelete || !password" class="btn-danger flex-1">Delete forever</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </section>
                    @endunless
                </div>
                <div x-show="tab === '2fa'" x-cloak class="space-y-6 p-5">
                    <div>
                        <h3 class="text-base font-semibold text-ink">Two-Factor Authentication</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Add an extra layer of security to your account</p>
                    </div>
                    @if(!auth()->user()->two_factor_secret)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                            <h4 class="font-medium text-slate-900 dark:text-white">Enable Two-Factor Authentication</h4>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                Secure your account by requiring a verification code from your authenticator app when logging in.
                            </p>
                            <form method="POST" action="{{ route('settings.2fa.enable') }}" class="mt-4 space-y-3">
                                @csrf
                                <div>
                                    <label for="fsettin-7" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Confirm your password</label>
                                    <input id="fsettin-7" type="password" name="password" class="input mt-1" required>
                                </div>
                                <button type="submit" class="btn-primary btn-sm">
                                    Enable 2FA
                                </button>
                            </form>
                        </div>
                    @else
                        @if(!auth()->user()->two_factor_confirmed_at)
                            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/30 dark:bg-blue-900/20">
                                <h4 class="font-medium text-blue-900 dark:text-blue-100">Complete Setup</h4>
                                <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">
                                    Scan the QR code below with your authenticator app (Google Authenticator, Authy, etc.)
                                </p>
                                <div class="mt-4 flex items-center justify-center bg-white p-4 rounded-lg">
                                    @php
                                        $google2fa = new \PragmaRX\Google2FA\Google2FA();
                                        $secret = decrypt(auth()->user()->two_factor_secret);
                                        $qrCodeUrl = $google2fa->getQRCodeUrl(
                                            config('app.name'),
                                            auth()->user()->email,
                                            $secret
                                        );
                                    @endphp
                                    {!! QrCode::size(200)->generate($qrCodeUrl) !!}
                                </div>
                                <div class="mt-4 p-3 bg-slate-800 rounded-lg">
                                    <p class="text-xs text-slate-400 text-center">Secret Key (manual entry)</p>
                                    <p class="text-sm font-mono text-white text-center">{{ $secret }}</p>
                                </div>
                                <form method="POST" action="{{ route('settings.2fa.confirm') }}" class="mt-4 space-y-3">
                                    @csrf
                                    <div>
                                        <label for="fsettin-8" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Enter 6-digit code from your app</label>
                                        <input id="fsettin-8" type="text" name="code" maxlength="6" pattern="[0-9]{6}" class="input mt-1" required>
                                    </div>
                                    <button type="submit" class="btn-primary btn-sm">
                                        Confirm & Activate
                                    </button>
                                </form>
                            </div>
                        @else
                            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900/30 dark:bg-green-900/20">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <h4 class="font-medium text-green-900 dark:text-green-100">Two-Factor Authentication is Active</h4>
                                </div>
                                <p class="mt-1 text-sm text-green-800 dark:text-green-200">
                                    Your account is protected with two-factor authentication.
                                </p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                <h4 class="font-medium text-slate-900 dark:text-white">Recovery Codes</h4>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    Store these codes in a safe place. You can use them to access your account if you lose your authenticator device.
                                </p>
                                @php
                                    $recoveryCodes = json_decode(decrypt(auth()->user()->two_factor_recovery_codes), true);
                                @endphp
                                <div class="mt-4 grid grid-cols-2 gap-2 p-3 bg-slate-800 rounded-lg">
                                    @foreach($recoveryCodes as $code)
                                        <div class="text-sm font-mono text-white">{{ $code }}</div>
                                    @endforeach
                                </div>
                                <form method="POST" action="{{ route('settings.2fa.recovery-codes') }}" class="mt-4" data-confirm="Are you sure you want to regenerate recovery codes? Your old codes will no longer work.">
                                    @csrf
                                    <input type="hidden" name="password" value="">
                                    <button type="button" data-prompt-password="Enter your password to regenerate codes:" class="rounded-lg bg-slate-600 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-500">
                                        Regenerate Codes
                                    </button>
                                </form>
                            </div>
                            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/30 dark:bg-red-900/20">
                                <h4 class="font-medium text-red-900 dark:text-red-100">Disable Two-Factor Authentication</h4>
                                <p class="mt-1 text-sm text-red-800 dark:text-red-200">
                                    Remove two-factor authentication from your account. This will make your account less secure.
                                </p>
                                <form method="POST" action="{{ route('settings.2fa.disable') }}" class="mt-4" data-confirm="Are you sure you want to disable two-factor authentication?">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="password" value="">
                                    <button type="button" data-prompt-password="Enter your password to disable 2FA:" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                        Disable 2FA
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endif
                </div>
                <div x-show="tab === 'backup'" x-cloak class="space-y-6 p-5" x-data="{ showFilters: false }">
                    <div>
                        <h3 class="text-base font-semibold text-ink">Backup & Export</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Download your complete link data with analytics in CSV format</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-slate-900 dark:text-white">Export All Data</h4>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    Export links with complete analytics and statistics
                                </p>
                            </div>
                            <button type="button" @click="showFilters = !showFilters" class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400">
                                <span x-text="showFilters ? 'Hide Filters' : 'Show Filters'"></span>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('settings.export') }}" class="mt-4 space-y-4">
                            @csrf
                            <div x-show="showFilters" x-cloak class="space-y-4 p-4 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="fsettin-9" class="block text-sm font-medium text-slate-700 dark:text-slate-300">From Date</label>
                                        <input id="fsettin-9" type="date" name="date_from" class="input mt-1">
                                    </div>
                                    <div>
                                        <label for="fsettin-10" class="block text-sm font-medium text-slate-700 dark:text-slate-300">To Date</label>
                                        <input id="fsettin-10" type="date" name="date_to" class="input mt-1">
                                    </div>
                                    <div>
                                        <label for="fsettin-11" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Minimum Clicks</label>
                                        <input id="fsettin-11" type="number" name="min_clicks" min="0" placeholder="e.g., 10" class="input mt-1">
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Only links with this many clicks or more</p>
                                    </div>
                                    <div>
                                        <label for="fsettin-12" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Sort By</label>
                                        <select id="fsettin-12" name="sort_by" class="input mt-1">
                                            <option value="newest">Newest First</option>
                                            <option value="oldest">Oldest First</option>
                                            <option value="most_clicked">Most Clicked First</option>
                                            <option value="least_clicked">Least Clicked First</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="fsettin-13" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Folder</label>
                                        <select id="fsettin-13" name="folder_id" class="input mt-1">
                                            <option value="">All Folders</option>
                                            <option value="default">Default (No Folder)</option>
                                            @foreach($folders as $folder)
                                                <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="fsettin-14" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Tag</label>
                                        <select id="fsettin-14" name="tag_id" class="input mt-1">
                                            <option value="">All Tags</option>
                                            @foreach($tags as $tag)
                                                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-primary btn-sm">
                                    <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Download Complete Export
                                </button>
                                <button type="button" @click="showFilters = false; $el.closest('form').reset();" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600">
                                    Reset Filters
                                </button>
                            </div>
                        </form>
                        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <p class="text-xs text-blue-800 dark:text-blue-200">
                                <strong>Complete export includes:</strong> Short URL, Destination URL, Redirect type, Folder, Tags, Password status, Status, Total clicks, Unique visitors, Created date, Expiration date, Last clicked date
                            </p>
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="font-medium text-slate-900 dark:text-white">Download My Account Data</h4>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    Profile, links, folders, tags, bio pages with their blocks, recent sign-ins and activity log (JSON)
                                </p>
                            </div>
                            <form method="POST" action="{{ route('settings.export.account') }}">
                                @csrf
                                <button type="submit" class="btn-primary btn-sm">Download JSON</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div x-show="tab === 'notifications'" x-cloak class="space-y-6 p-5">
                    <div>
                        <h3 class="text-base font-semibold text-ink">Notification Preferences</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Manage how you receive notifications</p>
                    </div>
                    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900/30 dark:bg-yellow-900/20">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            📧 Email notifications will be available once the emailing feature is configured. You'll be able to receive alerts for:
                        </p>
                        <ul class="mt-2 text-sm text-yellow-800 dark:text-yellow-200 space-y-1">
                            <li>• New link clicks</li>
                            <li>• Suspicious activity</li>
                            <li>• Account security alerts</li>
                            <li>• Weekly/monthly reports</li>
                        </ul>
                    </div>
                </div>
                <div x-show="tab === 'activity'" x-cloak class="space-y-6 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-ink">Activity Logs</h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400">Recent actions performed on your account (max 1 month)</p>
                        </div>
                    </div>
                    <form method="GET" action="{{ route('settings') }}#activity" class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                        <input type="hidden" name="tab" value="activity">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="fsettin-15" class="block text-sm font-medium text-slate-700 dark:text-slate-300">From Date</label>
                                <input id="fsettin-15" type="date" name="activity_from" value="{{ request('activity_from') }}" class="input mt-1">
                            </div>
                            <div>
                                <label for="fsettin-16" class="block text-sm font-medium text-slate-700 dark:text-slate-300">To Date</label>
                                <input id="fsettin-16" type="date" name="activity_to" value="{{ request('activity_to') }}" class="input mt-1">
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="btn-primary btn-sm">
                                Apply Filter
                            </button>
                            <a href="{{ route('settings') }}#activity" class="inline-flex items-center rounded-lg border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                Clear
                            </a>
                        </div>
                    </form>
                    <div class="space-y-2">
                        @forelse ($activityLogs as $log)
                            <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $log->description }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y H:i:s') }}</p>
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                        {{ ucfirst($log->action) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500 dark:text-slate-400">No activity logs found for the selected date range.</p>
                        @endforelse
                    </div>
                </div>
                <div x-show="tab === 'login'" x-cloak class="space-y-6 p-5">
                    <div>
                        <h3 class="text-base font-semibold text-ink">Login History</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Last 5 login sessions to your account</p>
                    </div>
                    @forelse($loginHistories as $index => $history)
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-sm font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ $index + 1 }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                                            {{ \Carbon\Carbon::parse($history->logged_in_at)->format('M d, Y - H:i:s') }}
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($history->logged_in_at)->diffForHumans() }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($history->is_proxy)
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400" title="Proxy confidence: {{ $history->proxy_confidence }}%">
                                            ⚠ {{ ucfirst($history->proxy_type) }}
                                        </span>
                                    @endif
                                    @if($index === 0)
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                            Current
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                                <div>
                                    <p class="font-medium text-slate-500 dark:text-slate-400">IP Address</p>
                                    <p class="text-slate-900 dark:text-white">{{ $history->ip_address ?? 'Unknown' }}</p>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-500 dark:text-slate-400">Location</p>
                                    <p class="text-slate-900 dark:text-white">
                                        @if($history->city && $history->country)
                                            {!! country_flag($history->country) !!} {{ $history->city }}, {{ country_name($history->country) }}
                                        @elseif($history->country)
                                            {!! country_flag($history->country) !!} {{ country_name($history->country) }}
                                        @else
                                            Unknown
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-500 dark:text-slate-400">Provider</p>
                                    <p class="text-slate-900 dark:text-white truncate" title="{{ $history->isp ?? $history->provider ?? 'Unknown' }}">
                                        {{ \Illuminate\Support\Str::limit($history->isp ?? $history->provider ?? 'Unknown', 20) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-500 dark:text-slate-400">Device</p>
                                    <p class="text-slate-900 dark:text-white">{{ $history->device ?? 'Desktop' }}</p>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-500 dark:text-slate-400">Browser</p>
                                    <p class="text-slate-900 dark:text-white">{{ $history->browser ?? 'Unknown' }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">No login history available yet.</p>
                    @endforelse
                </div>
                </div>
                <!-- API & Integrations Tab -->
                <div x-show="tab === 'api'" x-cloak x-data="apiSettings()" class="space-y-6 p-5">
                    <div>
                        <h3 class="text-base font-semibold text-ink">API Tokens</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Create API tokens for ShareX, CLI tools, and custom integrations</p>
                    </div>
                    <!-- Create New Token Form -->
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Create New Token</h4>
                        <form method="POST" action="{{ route('api-tokens.store') }}" @submit.prevent="createToken" class="space-y-3">
                            @csrf
                            <div>
                                <label for="fsettin-17" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Token Name</label>
                                <input id="fsettin-17" 
                                    type="text" 
                                    name="name" 
                                    x-model="newTokenName"
                                    placeholder="ShareX Desktop, My CLI Tool, etc." 
                                    required
                                    class="input"
                                >
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Give your token a memorable name</p>
                            </div>
                            <button type="submit" class="btn-primary btn-sm">
                                <span x-show="!isCreating">🔑 Generate Token</span>
                                <span x-show="isCreating">⏳ Generating...</span>
                            </button>
                        </form>
                    </div>
                    <!-- Show Newly Created Token (one time) -->
                    <div x-show="newlyCreatedToken" x-cloak class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 dark:border-emerald-700 dark:bg-emerald-900/20">
                        <h4 class="text-sm font-semibold text-emerald-900 dark:text-emerald-200 mb-2">✅ Token Created Successfully!</h4>
                        <p class="text-xs text-emerald-800 dark:text-emerald-300 mb-3">⚠️ Copy this token now. You won't be able to see it again!</p>
                        <div class="flex gap-2">
                            <input 
                                type="text" 
                                :value="newlyCreatedToken" 
                                readonly 
                                class="flex-1 rounded-lg border-emerald-300 bg-white px-3 py-2 text-sm font-mono text-emerald-900 dark:border-emerald-600 dark:bg-emerald-950 dark:text-emerald-100"
                            >
                            <button 
                                @click="copyToken(newlyCreatedToken)" 
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                            >
                                <span x-show="!copied">📋 Copy</span>
                                <span x-show="copied">✅ Copied!</span>
                            </button>
                        </div>
                    </div>
                    <!-- Existing Tokens List -->
                    <div>
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Your API Tokens</h4>
                        <div class="space-y-3">
                            @forelse(auth()->user()->apiTokens as $token)
                                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                                    <div class="flex-1">
                                        <h5 class="font-semibold text-slate-900 dark:text-white">{{ $token->name }}</h5>
                                        <div class="mt-1 flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                                            <span>Created: {{ $token->created_at->format('M d, Y') }}</span>
                                            @if($token->last_used_at)
                                                <span>Last used: {{ $token->last_used_at->diffForHumans() }}</span>
                                            @else
                                                <span class="text-amber-600 dark:text-amber-400">Never used</span>
                                            @endif
                                            <span>Rate limit: {{ $token->rate_limit }}/hour</span>
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('api-tokens.destroy', $token) }}" data-confirm="Are you sure you want to revoke this token? This action cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20">
                                            🗑️ Revoke
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500 dark:text-slate-400">No API tokens yet. Create one to get started!</p>
                            @endforelse
                        </div>
                    </div>
                    <!-- API Documentation Link -->
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                        <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-2">📚 API Documentation</h4>
                        <p class="text-sm text-blue-800 dark:text-blue-300 mb-3">Learn how to integrate hel.ink with ShareX, CLI tools, and more.</p>
                        <a href="{{ route('api-docs') }}" class="btn-primary btn-sm">
                            View API Docs →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script nonce="{{ Vite::cspNonce() }}">
    function apiSettings() {
        return {
            newTokenName: '',
            newlyCreatedToken: '',
            isCreating: false,
            copied: false,
            async createToken(event) {
                this.isCreating = true;
                try {
                    const formData = new FormData(event.target);
                    const response = await fetch('{{ route("api-tokens.store") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: formData
                    });
                    const data = await response.json();
                    if (response.ok && data.token) {
                        this.newlyCreatedToken = data.token;
                        this.newTokenName = '';
                        // Reload page after 10 seconds to show new token in list
                        setTimeout(() => {
                            window.location.reload();
                        }, 10000);
                    } else {
                        alert('Failed to create token: ' + (data.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error creating token:', error);
                    alert('An error occurred while creating the token');
                } finally {
                    this.isCreating = false;
                }
            },
            copyToken(token) {
                navigator.clipboard.writeText(token).then(() => {
                    this.copied = true;
                    setTimeout(() => {
                        this.copied = false;
                    }, 2000);
                });
            }
        }
    }
    </script>
</x-app-layout>
