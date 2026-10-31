@props(['link' => null, 'model' => false, 'prefix' => 'link-warning'])
<div class="space-y-3">
    <div>
        <label for="{{ $prefix }}" class="label">{{ __('Content warning') }}</label>
        <select id="{{ $prefix }}" name="content_warning" class="input" @if($model) x-model="formData.content_warning" @endif>
            @foreach(['none' => 'None', 'sensitive' => 'Sensitive content', 'adult' => 'Adult 18+'] as $value => $label)
                <option value="{{ $value }}" @selected(old('content_warning', $link?->content_warning ?? 'none') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $prefix }}-reason" class="label">{{ __('Reason (optional)') }}</label>
        <input id="{{ $prefix }}-reason" name="warning_reason" maxlength="255" class="input" value="{{ old('warning_reason', $link?->warning_reason) }}" @if($model) x-model="formData.warning_reason" @endif>
    </div>
</div>
