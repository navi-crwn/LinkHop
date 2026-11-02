@props(['link' => null, 'model' => false, 'section' => false])
@php
    use App\Support\DeepLinks;

    // In the create modal the values live in its formData (JSON is sent); on the edit page they are plain form fields.
    $app = $model ? 'formData.app_links' : 'app';
    $saved = old('app_links', $link?->app_links ?? []);
    $values = [
        'mode' => in_array($saved['mode'] ?? '', DeepLinks::MODES, true) ? $saved['mode'] : 'off',
        'ios' => (string) ($saved['ios'] ?? ''),
        'android' => (string) ($saved['android'] ?? ''),
        'ios_store' => (string) ($saved['ios_store'] ?? ''),
        'android_store' => (string) ($saved['android_store'] ?? ''),
    ];
    $problems = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'app_links'))->flatten()->unique();
    $recognised = $link ? DeepLinks::recognised($link->target_url) : null;
@endphp
@if ($section)
    <div class="grid gap-4 p-5 md:grid-cols-3">
        <div>
            <h2 class="section-title">{{ __('Open in the app') }}</h2>
            <p class="mt-1 text-xs text-muted">{{ __('On iOS and Android, try the installed app first, and fall back to the page or the app store.') }}</p>
        </div>
        <div class="md:col-span-2">
@endif
<div @unless ($model) x-data="{ app: @js($values) }" @endunless class="space-y-4">
    @if ($problems->isNotEmpty())
        <ul role="alert" class="space-y-1 rounded-xl bg-rose-500/10 px-3 py-2 text-sm text-rose-700 dark:text-rose-300">
            @foreach ($problems as $problem)
                <li>{{ $problem }}</li>
            @endforeach
        </ul>
    @endif
    <div>
        @unless ($section)
            <h3 class="text-sm font-medium text-ink">{{ __('Open in the app') }}</h3>
        @endunless
        <label for="{{ $model ? 'modal-app-mode' : 'app_links_mode' }}" class="label">{{ __('When someone opens this link on a phone') }}</label>
        <select id="{{ $model ? 'modal-app-mode' : 'app_links_mode' }}" @unless ($model) name="app_links[mode]" @endunless x-model="{{ $app }}.mode" class="input">
            <option value="off">{{ __('Just go to the page') }}</option>
            <option value="auto">{{ __('Open the app when the page belongs to a known app') }}</option>
            <option value="custom">{{ __('Open the app address I give') }}</option>
        </select>
        <p class="mt-1 text-xs text-muted" x-show="{{ $app }}.mode === 'auto'" x-cloak>
            {{ __('Known: YouTube, Spotify, WhatsApp, Telegram, Instagram profiles, X, TikTok profiles, Zoom meetings, App Store and Google Play.') }}
            @if ($recognised)
                <strong class="text-ink">{{ __('This destination is recognised as :app.', ['app' => $recognised]) }}</strong>
            @elseif ($link)
                {{ __('This destination is not recognised: nothing would open. Choose the option with your own app address instead.') }}
            @endif
        </p>
    </div>
    <div class="space-y-3" x-show="{{ $app }}.mode === 'custom'" x-cloak>
        <div>
            <label for="{{ $model ? 'modal-app-ios' : 'app_links_ios' }}" class="label">{{ __('App address on iOS') }}</label>
            <input id="{{ $model ? 'modal-app-ios' : 'app_links_ios' }}" type="text" @unless ($model) name="app_links[ios]" @endunless x-model="{{ $app }}.ios" class="input" dir="ltr" placeholder="myapp://item/42" autocomplete="off" spellcheck="false">
        </div>
        <div>
            <label for="{{ $model ? 'modal-app-android' : 'app_links_android' }}" class="label">{{ __('App address on Android') }}</label>
            <input id="{{ $model ? 'modal-app-android' : 'app_links_android' }}" type="text" @unless ($model) name="app_links[android]" @endunless x-model="{{ $app }}.android" class="input" dir="ltr" placeholder="myapp://item/42" autocomplete="off" spellcheck="false">
            <p class="mt-1 text-xs text-muted">{{ __('A URL scheme (myapp://...), an https app link, or an Android intent address (intent://...#Intent;scheme=https;package=com.example.app;end).') }}</p>
        </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2" x-show="{{ $app }}.mode !== 'off'" x-cloak>
        <div>
            <label for="{{ $model ? 'modal-app-ios-store' : 'app_links_ios_store' }}" class="label">{{ __('App Store page (optional)') }}</label>
            <input id="{{ $model ? 'modal-app-ios-store' : 'app_links_ios_store' }}" type="url" @unless ($model) name="app_links[ios_store]" @endunless x-model="{{ $app }}.ios_store" class="input" dir="ltr" placeholder="https://apps.apple.com/app/id123456789">
        </div>
        <div>
            <label for="{{ $model ? 'modal-app-android-store' : 'app_links_android_store' }}" class="label">{{ __('Google Play page (optional)') }}</label>
            <input id="{{ $model ? 'modal-app-android-store' : 'app_links_android_store' }}" type="url" @unless ($model) name="app_links[android_store]" @endunless x-model="{{ $app }}.android_store" class="input" dir="ltr" placeholder="https://play.google.com/store/apps/details?id=com.example.app">
        </div>
        <p class="text-xs text-muted sm:col-span-2">{{ __('Visitors without the app go to its store page when you give one, otherwise to the destination. Desktop visitors and crawlers always go to the destination.') }}</p>
    </div>
</div>
@if ($section)
        </div>
    </div>
@endif
