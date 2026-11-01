@props(['link' => null, 'model' => false, 'section' => false])
@php
    use App\Support\AbSplit;
    use App\Support\LinkRouting;

    // In the create modal the rows live in its formData (JSON is sent); on the edit page they are plain form fields.
    $rulesVar = $model ? 'formData.routing_rules' : 'rules';
    $variantsVar = $model ? 'formData.ab_variants' : 'variants';
    $list = fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v;
    $rules = collect($model ? [] : old('routing_rules', $link?->routing_rules ?? []))->filter(fn ($r) => is_array($r))->map(fn ($r) => [
        'country' => $list($r['country'] ?? ''),
        'language' => $list($r['language'] ?? ''),
        'device' => $r['device'] ?? 'any',
        'os' => $r['os'] ?? 'any',
        'browser' => $r['browser'] ?? 'any',
        'url' => (string) ($r['url'] ?? ''),
    ])->values()->all();
    $variants = collect($model ? [] : old('ab_variants', $link?->ab_variants ?? []))->filter(fn ($r) => is_array($r))->map(fn ($v) => [
        'url' => (string) ($v['url'] ?? ''),
        'weight' => $v['weight'] ?? 1,
    ])->values()->all();
    $problems = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'routing_rules') || str_starts_with($k, 'ab_variants'))->flatten()->unique();
    $devices = ['any' => __('Any device'), 'mobile' => __('Mobile'), 'tablet' => __('Tablet'), 'desktop' => __('Desktop')];
    $systems = ['any' => __('Any system'), 'ios' => 'iOS', 'android' => 'Android', 'windows' => 'Windows', 'macos' => 'macOS', 'linux' => 'Linux', 'chromeos' => 'ChromeOS'];
    $browsers = ['any' => __('Any browser'), 'chrome' => 'Chrome', 'safari' => 'Safari', 'firefox' => 'Firefox', 'edge' => 'Edge', 'opera' => 'Opera', 'samsung' => 'Samsung Internet'];
    $newRule = "{ country: '', language: '', device: 'any', os: 'any', browser: 'any', url: '' }";
    $weightTotal = "{$variantsVar}.reduce((a, v) => a + (parseInt(v.weight) || 0), 0)";
@endphp
@if ($section)
    <div class="grid gap-4 p-5 md:grid-cols-3">
        <div>
            <h2 class="section-title">{{ __('Routing and split') }}</h2>
            <p class="mt-1 text-xs text-muted">{{ __('Send some visitors somewhere else, or test two or more addresses against each other.') }}</p>
        </div>
        <div class="md:col-span-2">
@endif
<div @unless ($model) x-data="{ rules: @js($rules), variants: @js($variants) }" @endunless class="space-y-5">
    @if ($problems->isNotEmpty())
        <ul role="alert" class="space-y-1 rounded-xl bg-rose-500/10 px-3 py-2 text-sm text-rose-700 dark:text-rose-300">
            @foreach ($problems as $problem)
                <li>{{ $problem }}</li>
            @endforeach
        </ul>
    @endif

    <div class="space-y-3">
        <div>
            <h3 class="text-sm font-medium text-ink">{{ __('Routing rules') }}</h3>
            <p class="mt-1 text-xs text-muted">{{ __('The first rule that fits the visitor decides; everyone else goes to the destination above. A rule can combine conditions: all of them have to fit. Crawlers always get the destination above.') }}</p>
        </div>
        <template x-for="(rule, i) in {{ $rulesVar }}" :key="i">
            <fieldset class="space-y-3 rounded-xl border border-slate-500/20 p-3">
                <legend class="sr-only" x-text="@js(__('Rule')) + ' ' + (i + 1)"></legend>
                <div class="flex items-center justify-between gap-2">
                    <span class="chip-gray" x-text="@js(__('Rule')) + ' ' + (i + 1)"></span>
                    <div class="flex items-center gap-1">
                        <button type="button" class="btn-ghost btn-sm" x-bind:disabled="i === 0" x-on:click="{{ $rulesVar }}.splice(i - 1, 2, {{ $rulesVar }}[i], {{ $rulesVar }}[i - 1])" aria-label="{{ __('Move up') }}" title="{{ __('Move up') }}">↑</button>
                        <button type="button" class="btn-ghost btn-sm" x-bind:disabled="i === {{ $rulesVar }}.length - 1" x-on:click="{{ $rulesVar }}.splice(i, 2, {{ $rulesVar }}[i + 1], {{ $rulesVar }}[i])" aria-label="{{ __('Move down') }}" title="{{ __('Move down') }}">↓</button>
                        <button type="button" class="btn-ghost btn-sm" x-on:click="{{ $rulesVar }}.splice(i, 1)">{{ __('Remove') }}</button>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">{{ __('Countries') }}</label>
                        <input type="text" x-model="rule.country" x-bind:name="'routing_rules[' + i + '][country]'" maxlength="120" placeholder="DE, AT, CH" class="input">
                    </div>
                    <div>
                        <label class="label">{{ __('Languages') }}</label>
                        <input type="text" x-model="rule.language" x-bind:name="'routing_rules[' + i + '][language]'" maxlength="120" placeholder="de, pt-BR" class="input">
                    </div>
                    <div>
                        <label class="label">{{ __('Device') }}</label>
                        <select x-model="rule.device" x-bind:name="'routing_rules[' + i + '][device]'" class="input">
                            @foreach ($devices as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">{{ __('System') }}</label>
                        <select x-model="rule.os" x-bind:name="'routing_rules[' + i + '][os]'" class="input">
                            @foreach ($systems as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">{{ __('Browser') }}</label>
                        <select x-model="rule.browser" x-bind:name="'routing_rules[' + i + '][browser]'" class="input sm:w-1/2!">
                            @foreach ($browsers as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">{{ __('Send them to') }}</label>
                    <input type="text" inputmode="url" x-model="rule.url" x-bind:name="'routing_rules[' + i + '][url]'" maxlength="2000" placeholder="https://" class="input">
                </div>
            </fieldset>
        </template>
        <p x-show="{{ $rulesVar }}.length === 0" class="text-xs text-muted">{{ __('No rules: everyone goes to the destination above.') }}</p>
        <button type="button" class="btn-soft btn-sm" x-bind:disabled="{{ $rulesVar }}.length >= {{ LinkRouting::MAX_RULES }}" x-on:click="{{ $rulesVar }}.push({{ $newRule }})">{{ __('Add a rule') }}</button>
        <p class="text-xs text-muted" x-show="{{ $rulesVar }}.length >= {{ LinkRouting::MAX_RULES }}" x-cloak>{{ __('A link can have :max rules at most.', ['max' => LinkRouting::MAX_RULES]) }}</p>
    </div>

    <div class="space-y-3">
        <div>
            <h3 class="text-sm font-medium text-ink">{{ __('A/B split') }}</h3>
            <p class="mt-1 text-xs text-muted">{{ __('Share visitors between :min and :max addresses by weight. A returning visitor sees the same address. A rule that fits comes first; crawlers get the destination above.', ['min' => AbSplit::MIN, 'max' => AbSplit::MAX]) }}</p>
        </div>
        <template x-for="(variant, i) in {{ $variantsVar }}" :key="i">
            <div class="grid grid-cols-[auto_1fr_auto] items-end gap-x-2 gap-y-2 sm:grid-cols-[auto_1fr_5.5rem_auto]">
                <span class="chip-blue mb-2.5" x-text="String.fromCharCode(65 + i)"></span>
                <div class="min-w-0">
                    <label class="label">{{ __('Address') }}</label>
                    <input type="text" inputmode="url" x-model="variant.url" x-bind:name="'ab_variants[' + i + '][url]'" maxlength="2000" placeholder="https://" class="input">
                </div>
                <div class="order-last col-span-2 col-start-2 sm:order-none sm:col-span-1 sm:col-start-auto">
                    <label class="label">{{ __('Weight') }} <span class="font-normal" x-text="'(' + ({{ $weightTotal }} > 0 ? Math.round((parseInt(variant.weight) || 0) / {{ $weightTotal }} * 100) : 0) + '%)'"></span></label>
                    <input type="number" min="1" max="{{ AbSplit::MAX_WEIGHT }}" inputmode="numeric" x-model="variant.weight" x-bind:name="'ab_variants[' + i + '][weight]'" class="input">
                </div>
                <button type="button" class="btn-ghost btn-sm mb-1" x-on:click="{{ $variantsVar }}.splice(i, 1)" aria-label="{{ __('Remove') }}">✕</button>
            </div>
        </template>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn-soft btn-sm" x-bind:disabled="{{ $variantsVar }}.length >= {{ AbSplit::MAX }}" x-on:click="{{ $variantsVar }}.push({ url: '', weight: 50 })">{{ __('Add a variant') }}</button>
            <p class="text-xs text-muted" x-show="{{ $variantsVar }}.length === 1" x-cloak>{{ __('Add at least one more address to start the split.') }}</p>
        </div>
        @if ($link && ! $model)
            <x-ab-results :link="$link" class="rounded-xl border border-slate-500/20 p-3" />
        @endif
    </div>
</div>
@if ($section)
        </div>
    </div>
@endif
