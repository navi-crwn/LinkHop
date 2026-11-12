<x-app-layout>
    <x-slot name="pageTitle">{{ __('Settings') }}</x-slot>
    @php
        $settingsTabs = [
            'hopid' => [__('HOP ID'), 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
            'backup' => [__('Backup & export'), 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'],
            'notifications' => [__('Notifications'), 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
            'webhooks' => [__('Webhooks'), 'M13 10V3L4 14h7v7l9-11h-7z'],
            'activity' => [__('Activity log'), 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
        ];
    @endphp

    <x-page-header :title="__('Settings')" :subtitle="__('Your LinkHop data; the account itself is your HOP ID at the hub.')" />

    <div class="grid gap-4 lg:grid-cols-[13rem_minmax(0,1fr)]" x-data="{
        tab: '{{ session('active_tab', 'hopid') }}',
        init() {
            const hashTab = window.location.hash.substring(1);
            if (@js(array_keys($settingsTabs)).includes(hashTab)) {
                this.tab = hashTab;
            }
            this.$watch('tab', value => { history.replaceState(null, '', '#' + value); this.reveal(); });
            this.$nextTick(() => this.reveal());
        },
        // On a phone the tabs are a scrolling row: bring the active one into view.
        reveal() {
            this.$refs.tabs?.querySelector('[aria-current=page]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
    }" @tab-changed.window="tab = $event.detail" @hashchange.window="if (@js(array_keys($settingsTabs)).includes(window.location.hash.substring(1))) tab = window.location.hash.substring(1)">
        <nav x-ref="tabs" class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 lg:sticky lg:top-24 lg:mx-0 lg:flex-col lg:self-start lg:overflow-visible lg:px-0" aria-label="{{ __('Settings sections') }}">
            @foreach ($settingsTabs as $key => [$label, $icon])
                <button type="button" @click="tab = '{{ $key }}'" data-ctl="tab" class="nav-item shrink-0" :class="tab === '{{ $key }}' && 'nav-item-active'" :aria-current="tab === '{{ $key }}' ? 'page' : null">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    {{ $label }}
                </button>
            @endforeach
        </nav>
        <div class="min-w-0 space-y-4">
            <x-flash />
            <div class="glass rounded-2xl">
                <div x-show="tab === 'hopid'" class="space-y-4 p-5">
                    <div>
                        <h3 class="text-base font-semibold text-ink">{{ __('HOP ID (your account)') }}</h3>
                        <p class="text-sm text-muted">{{ __('One account for every HOP app. Profile, password, two-factor, sign-in history, API tokens, data download and account deletion live at the hub.') }}</p>
                    </div>
                    {{-- Each card opens the matching tab of the hub's settings page (its tabs: account, 2fa, api, backup). --}}
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            [__('Profile'), __('Name, username, photo, email.'), hub_url('settings').'#account'],
                            [__('Security'), __('Password and two-factor authentication.'), hub_url('settings').'#2fa'],
                            [__('API tokens'), __('Tokens with link:* scopes for ShareX, the CLI and BioHop.'), hub_url('settings').'#api'],
                            [__('Your data'), __('Download everything the HOP apps hold about you, or delete the account.'), hub_url('settings').'#backup'],
                        ] as [$title, $text, $href])
                            <a href="{{ $href }}" class="glass-inset block rounded-xl p-4 transition hover:bg-slate-500/5">
                                <h4 class="font-medium text-ink">{{ $title }} <span class="text-muted">&rarr;</span></h4>
                                <p class="mt-1 text-sm text-muted">{{ $text }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
                <div x-show="tab === 'backup'" class="space-y-4 p-5" x-data="{ showFilters: false }">
                    <div>
                        <h3 class="text-base font-semibold text-ink">{{ __('Backup & export') }}</h3>
                        <p class="text-sm text-muted">{{ __('Download your complete link data with analytics in CSV format') }}</p>
                    </div>
                    <div class="glass-inset rounded-xl p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h4 class="font-medium text-ink">{{ __('Export all data') }}</h4>
                                <p class="mt-1 text-sm text-muted">
                                    {{ __('Export links with complete analytics and statistics') }}
                                </p>
                            </div>
                            <button type="button" @click="showFilters = !showFilters" class="btn-link" data-ctl="link">
                                <span x-text="showFilters ? @js(__('Hide filters')) : @js(__('Show filters'))">{{ __('Show filters') }}</span>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('settings.export') }}" class="mt-4 space-y-4">
                            @csrf
                            <div x-show="showFilters" x-cloak class="glass rounded-xl space-y-4 p-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="fsettin-9" class="label">{{ __('From date') }}</label>
                                        <input id="fsettin-9" type="date" name="date_from" class="input mt-1">
                                    </div>
                                    <div>
                                        <label for="fsettin-10" class="label">{{ __('To date') }}</label>
                                        <input id="fsettin-10" type="date" name="date_to" class="input mt-1">
                                    </div>
                                    <div>
                                        <label for="fsettin-11" class="label">{{ __('Minimum clicks') }}</label>
                                        <input id="fsettin-11" type="number" name="min_clicks" min="0" placeholder="{{ __('e.g., 10') }}" class="input mt-1">
                                        <p class="mt-1 text-xs text-muted">{{ __('Only links with this many clicks or more') }}</p>
                                    </div>
                                    <div>
                                        <label for="fsettin-12" class="label">{{ __('Sort by') }}</label>
                                        <select id="fsettin-12" name="sort_by" class="input mt-1">
                                            <option value="newest">{{ __('Newest first') }}</option>
                                            <option value="oldest">{{ __('Oldest first') }}</option>
                                            <option value="most_clicked">{{ __('Most clicked first') }}</option>
                                            <option value="least_clicked">{{ __('Least clicked first') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="fsettin-13" class="label">{{ __('Folder') }}</label>
                                        <select id="fsettin-13" name="folder_id" class="input mt-1">
                                            <option value="">{{ __('All folders') }}</option>
                                            <option value="default">{{ __('No folder') }}</option>
                                            @foreach($folders as $folder)
                                                <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="fsettin-14" class="label">{{ __('Tag') }}</label>
                                        <select id="fsettin-14" name="tag_id" class="input mt-1">
                                            <option value="">{{ __('All tags') }}</option>
                                            @foreach($tags as $tag)
                                                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="btn-primary btn-sm">
                                    <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    {{ __('Download complete export') }}
                                </button>
                                <button type="button" @click="showFilters = false; $el.closest('form').reset();" class="btn-ghost btn-sm">
                                    {{ __('Reset filters') }}
                                </button>
                            </div>
                        </form>
                        <div class="mt-4 rounded-xl bg-indigo-500/10 p-3">
                            <p class="text-xs text-muted">
                                <strong>{{ __('Complete export includes:') }}</strong> {{ __('Short URL, Destination URL, Redirect type, Folder, Tags, Password status, Status, Total clicks, Unique visitors, Created date, Expiration date, Last clicked date') }}
                            </p>
                        </div>
                    </div>
                </div>
                @include('partials.settings-webhooks')
                <div x-show="tab === 'notifications'" x-cloak class="space-y-4 p-5">
                    <div>
                        <h3 class="text-base font-semibold text-ink">{{ __('Notification preferences') }}</h3>
                        <p class="text-sm text-muted">{{ __('Manage how you receive notifications') }}</p>
                    </div>
                    <form method="POST" action="{{ route('settings.monitoring') }}" class="glass-inset rounded-xl p-4">
                        @csrf
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="monitor_destinations" value="1" class="mt-1 h-4 w-4 shrink-0 border-slate-300" @checked(auth()->user()->monitor_destinations)>
                            <span class="min-w-0">
                                <span class="block font-medium text-ink">{{ __('Check where my links point') }}</span>
                                <span class="mt-1 block text-sm text-muted">{{ __('Once a day LinkHop asks the address behind each of your live links whether it still answers. If one stops, you see a note on the link and get one email until it works again.') }}</span>
                            </span>
                        </label>
                        <button type="submit" class="btn-primary btn-sm mt-3">{{ __('Save') }}</button>
                    </form>
                    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900/30 dark:bg-yellow-900/20">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            📧 {{ __("More email notifications will be available once the emailing feature is configured. You'll be able to receive alerts for:") }}
                        </p>
                        <ul class="mt-2 text-sm text-yellow-800 dark:text-yellow-200 space-y-1">
                            <li>• {{ __('New link clicks') }}</li>
                            <li>• {{ __('Suspicious activity') }}</li>
                            <li>• {{ __('Account security alerts') }}</li>
                            <li>• {{ __('Weekly/monthly reports') }}</li>
                        </ul>
                    </div>
                </div>
                <div x-show="tab === 'activity'" x-cloak class="space-y-4 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-ink">{{ __('Activity log') }}</h3>
                            <p class="text-sm text-muted">{{ __('Recent actions performed on your account (max 1 month)') }}</p>
                        </div>
                    </div>
                    <form method="GET" action="{{ route('settings') }}#activity" class="glass-inset rounded-xl p-4">
                        <input type="hidden" name="tab" value="activity">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="fsettin-15" class="label">{{ __('From date') }}</label>
                                <input id="fsettin-15" type="date" name="activity_from" value="{{ $activityFrom }}" class="input mt-1">
                            </div>
                            <div>
                                <label for="fsettin-16" class="label">{{ __('To date') }}</label>
                                <input id="fsettin-16" type="date" name="activity_to" value="{{ $activityTo }}" class="input mt-1">
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="btn-primary btn-sm">
                                {{ __('Apply filter') }}
                            </button>
                            <a href="{{ route('settings') }}#activity" class="btn-ghost btn-sm">
                                {{ __('Clear') }}
                            </a>
                        </div>
                    </form>
                    <div class="space-y-2">
                        @forelse ($activityLogs as $log)
                            @php $at = \Carbon\Carbon::parse($log->created_at, 'UTC'); @endphp
                            <div class="glass-inset rounded-xl p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-ink break-words">{{ $log->description }}</p>
                                        {{-- Stored in UTC; shown in the reader's own time zone when a script runs, with the zone named either way.
                                             The parts are listed one by one: dateStyle/timeStyle with timeZoneName makes the formatter throw. --}}
                                        <time datetime="{{ $at->toIso8601ZuluString() }}" class="text-xs tabular-nums text-muted" x-data x-text="new Date($el.dateTime).toLocaleString(document.documentElement.lang || undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short' })">{{ $at->format('M d, Y H:i:s') }} UTC</time>
                                    </div>
                                    <span class="chip-gray shrink-0">
                                        {{ __(ucfirst($log->action)) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-muted">{{ __('No activity logs found for the selected date range.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
