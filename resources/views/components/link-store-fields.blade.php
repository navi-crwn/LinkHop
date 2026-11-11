@props(['link' => null, 'model' => false, 'section' => false])
@php
    use App\Support\StoreLinks;

    // In the create modal the values live in its formData (JSON is sent); on the edit page they are plain form fields.
    $onVar = $model ? 'formData.store_mode' : 'storeOn';
    $urlsVar = $model ? 'formData.store_links' : 'storeLinks';
    $uid = $model ? 'modal-store' : 'store';
    $saved = $model ? null : StoreLinks::fromRules($link?->routing_rules);
    $values = array_fill_keys(array_keys(StoreLinks::STORES), '');
    foreach ((array) ($model ? [] : old('store_links', $saved ?? [])) as $key => $url) {
        if (isset($values[$key]) && is_string($url)) {
            $values[$key] = $url;
        }
    }
    $on = $model ? false : (old('store_mode') !== null ? filter_var(old('store_mode'), FILTER_VALIDATE_BOOLEAN) : $saved !== null);
    $problems = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'store_'))->flatten()->unique();
    $labels = [
        'play' => __('Google Play (Android)'),
        'appstore' => __('App Store (iPhone and iPad)'),
        'macstore' => __('Mac App Store (macOS)'),
        'msstore' => __('Microsoft Store (Windows)'),
    ];
    $hasRules = ! $model && ! $on && ! empty($link?->routing_rules);
@endphp
@if ($section)
    <div class="grid gap-4 p-5 md:grid-cols-3">
        <div>
            <h2 class="section-title">{{ __('App store link') }}</h2>
            <p class="mt-1 text-xs text-muted">{{ __('One link for every system: each visitor goes to the app store of their own system.') }}</p>
        </div>
        <div class="md:col-span-2">
@endif
<div @unless ($model) x-data="{ storeOn: @js($on), storeLinks: @js($values) }" @endunless @if ($model) x-show="!formData.wa_mode" @endif class="space-y-4" data-store-links>
    @if ($problems->isNotEmpty())
        <ul role="alert" class="space-y-1 rounded-xl bg-rose-500/10 px-3 py-2 text-sm text-rose-700 dark:text-rose-300">
            @foreach ($problems as $problem)
                <li>{{ $problem }}</li>
            @endforeach
        </ul>
    @endif
    <div>
        @unless ($section)
            <h3 class="text-sm font-medium text-ink">{{ __('App store link') }}</h3>
        @endunless
        <label class="flex items-center gap-2 text-sm text-ink" for="{{ $uid }}-mode">
            <input id="{{ $uid }}-mode" type="checkbox" x-model="{{ $onVar }}" class="h-4 w-4 rounded border-slate-400">
            <span>{{ __('Send each system to its app store') }}</span>
        </label>
        @unless ($model)
            <input type="hidden" name="store_mode" value="1" x-bind:disabled="!{{ $onVar }}">
        @endunless
        @if ($hasRules)
            <p class="mt-1 text-xs text-muted" x-show="{{ $onVar }}" x-cloak>{{ __('Your routing rules are replaced by the store addresses when you save.') }}</p>
        @endif
    </div>
    <div class="space-y-3" x-show="{{ $onVar }}" x-cloak>
        @foreach ($labels as $key => $label)
            <div>
                <label for="{{ $uid }}-{{ $key }}" class="label">{{ $label }}</label>
                <input id="{{ $uid }}-{{ $key }}" type="text" inputmode="url" dir="ltr" autocomplete="off" spellcheck="false" maxlength="2000"
                    @unless ($model) name="store_links[{{ $key }}]" x-bind:disabled="!{{ $onVar }}" @endunless
                    x-model="{{ $urlsVar }}.{{ $key }}" placeholder="{{ StoreLinks::STORES[$key]['example'] }}" class="input">
            </div>
        @endforeach
        <p class="text-xs text-muted">{{ __('Each address is optional, but add at least one. Everyone else (Linux, ChromeOS, crawlers and any other system) goes to the destination above.') }}</p>
    </div>
</div>
@if ($section)
        </div>
    </div>
@endif
