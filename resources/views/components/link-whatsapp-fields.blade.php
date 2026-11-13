@props(['link' => null, 'model' => false, 'section' => false])
@php
    use App\Support\WhatsAppRotator;

    // In the create modal the values live in its formData (JSON is sent); on the edit page they are plain form fields.
    $modeVar = $model ? 'formData.wa_mode' : 'waMode';
    $listVar = $model ? 'formData.wa_numbers' : 'waNumbers';
    $uid = $model ? 'modal-wa' : 'wa';
    $savedMode = $model ? '' : (string) old('wa_mode', $link?->wa_mode ?? '');
    $savedMode = in_array($savedMode, WhatsAppRotator::MODES, true) ? $savedMode : '';
    $rows = collect($model ? [] : old('wa_numbers', $link?->wa_numbers ?? []))->filter(fn ($r) => is_array($r))->map(fn ($r) => [
        'number' => (string) ($r['number'] ?? ''),
        'weight' => $r['weight'] ?? 1,
        'message' => (string) ($r['message'] ?? ''),
    ])->values()->all();
    $problems = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'wa_'))->flatten()->unique();
@endphp
@if ($section)
    <div class="grid gap-4 p-5 md:grid-cols-3">
        <div>
            <h2 class="section-title">{{ __('WhatsApp rotator') }}</h2>
            <p class="mt-1 text-xs text-muted">{{ __('Share chats between several numbers: each visit opens WhatsApp with one of them.') }}</p>
        </div>
        <div class="md:col-span-2">
@endif
@if ($model)
    <p x-show="formData.store_mode" x-cloak class="rounded-xl bg-amber-500/10 px-3 py-2 text-sm text-ink" data-chip-blocked="wa">{{ __('The app store link is on, so the WhatsApp rotator is paused. Turn the app store link off in Open in the app to use the rotator.') }}</p>
@endif
<div @unless ($model) x-data="{ waMode: @js($savedMode), waNumbers: @js($rows) }" @endunless @if ($model) x-show="!formData.store_mode" @endif class="space-y-4">
    @if ($problems->isNotEmpty())
        <ul role="alert" class="space-y-1 rounded-xl bg-rose-500/10 px-3 py-2 text-sm text-rose-700 dark:text-rose-300">
            @foreach ($problems as $problem)
                <li>{{ $problem }}</li>
            @endforeach
        </ul>
    @endif
    <div>
        @unless ($section)
            <h3 class="text-sm font-medium text-ink">{{ __('WhatsApp rotator') }}</h3>
        @endunless
        <label for="{{ $uid }}-mode" class="label">{{ __('Send every visit to a WhatsApp number') }}</label>
        <select id="{{ $uid }}-mode" @unless ($model) name="wa_mode" @endunless x-model="{{ $modeVar }}" x-on:change="if ({{ $modeVar }} && {{ $listVar }}.length === 0) { {{ $listVar }}.push({ number: '', weight: 1, message: '' }) }" class="input">
            <option value="">{{ __('Off: use the destination above') }}</option>
            <option value="round_robin">{{ __('In turn (round robin)') }}</option>
            <option value="weighted">{{ __('By weight') }}</option>
        </select>
        <p class="mt-1 text-xs text-muted" x-show="{{ $modeVar }}" x-cloak>{{ __('The destination above is not used: visitors always go to one of the numbers. Routing rules, the split and the app opener are switched off.') }}</p>
    </div>
    <div class="space-y-3" x-show="{{ $modeVar }}" x-cloak>
        <template x-for="(row, i) in {{ $listVar }}" :key="i">
            <fieldset class="space-y-3 rounded-xl border border-slate-500/20 p-3">
                <legend class="sr-only" x-text="@js(__('Number')) + ' ' + (i + 1)"></legend>
                <div class="flex items-center justify-between gap-2">
                    <span class="chip-gray" x-text="@js(__('Number')) + ' ' + (i + 1)"></span>
                    <button type="button" class="btn-ghost btn-sm" x-on:click="{{ $listVar }}.splice(i, 1)">{{ __('Remove') }}</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-[1fr_6rem]">
                    <div>
                        <label class="label">{{ __('WhatsApp number') }}</label>
                        <input type="text" inputmode="tel" x-model="row.number" x-bind:name="'wa_numbers[' + i + '][number]'" maxlength="24" placeholder="+1 555 555 0101" dir="ltr" autocomplete="off" spellcheck="false" class="input">
                    </div>
                    <div x-show="{{ $modeVar }} === 'weighted'">
                        <label class="label">{{ __('Weight') }}</label>
                        <input type="number" min="1" max="{{ WhatsAppRotator::MAX_WEIGHT }}" inputmode="numeric" x-model="row.weight" x-bind:name="'wa_numbers[' + i + '][weight]'" class="input">
                    </div>
                </div>
                <div>
                    <label class="label">{{ __('Prefilled message (optional)') }}</label>
                    <textarea rows="2" x-model="row.message" x-bind:name="'wa_numbers[' + i + '][message]'" maxlength="{{ WhatsAppRotator::MAX_MESSAGE }}" class="input"></textarea>
                </div>
            </fieldset>
        </template>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn-soft btn-sm" x-bind:disabled="{{ $listVar }}.length >= {{ WhatsAppRotator::MAX_NUMBERS }}" x-on:click="{{ $listVar }}.push({ number: '', weight: 1, message: '' })">{{ __('Add a number') }}</button>
            <p class="text-xs text-muted">{{ __('Digits with the country code, no +. Up to :max numbers.', ['max' => WhatsAppRotator::MAX_NUMBERS]) }}</p>
        </div>
    </div>
</div>
@if ($section)
        </div>
    </div>
@endif
