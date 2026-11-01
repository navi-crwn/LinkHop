@props(['target' => 'target_url'])
@php
    // Presets live here, in code: nothing is stored on the server. Each fills the source and the medium.
    $presets = [
        ['key' => 'newsletter', 'label' => __('Newsletter'), 'source' => 'newsletter', 'medium' => 'email'],
        ['key' => 'email-signature', 'label' => __('Email signature'), 'source' => 'signature', 'medium' => 'email'],
        ['key' => 'facebook', 'label' => 'Facebook', 'source' => 'facebook', 'medium' => 'social'],
        ['key' => 'instagram', 'label' => 'Instagram', 'source' => 'instagram', 'medium' => 'social'],
        ['key' => 'x', 'label' => 'X', 'source' => 'x', 'medium' => 'social'],
        ['key' => 'linkedin', 'label' => 'LinkedIn', 'source' => 'linkedin', 'medium' => 'social'],
        ['key' => 'youtube', 'label' => 'YouTube', 'source' => 'youtube', 'medium' => 'video'],
        ['key' => 'google-ads', 'label' => 'Google Ads', 'source' => 'google', 'medium' => 'cpc'],
        ['key' => 'qr-print', 'label' => __('QR code on print'), 'source' => 'print', 'medium' => 'qr'],
    ];
@endphp
<div
    x-data="{
        open: false,
        target: @js($target),
        presets: @js($presets),
        preset: '',
        message: '',
        f: { source: '', medium: '', campaign: '', term: '', content: '' },
        field() { return document.getElementById(this.target); },
        toggle() {
            this.open = !this.open;
            this.message = '';
            if (this.open) { this.read(); }
        },
        read() {
            // Start from the tags already on the address.
            try {
                const u = new URL(this.field().value.trim());
                for (const k of Object.keys(this.f)) { this.f[k] = u.searchParams.get('utm_' + k) || this.f[k]; }
            } catch (e) {}
        },
        usePreset() {
            const p = this.presets.find(x => x.key === this.preset);
            if (p) { this.f.source = p.source; this.f.medium = p.medium; }
        },
        write(fn) {
            const el = this.field();
            let raw = el ? el.value.trim() : '';
            if (!raw) { this.message = @js(__('Enter the destination address first.')); return false; }
            if (!/^https?:\/\//i.test(raw)) { raw = 'https://' + raw; }
            try { const u = new URL(raw); fn(u); el.value = u.toString(); } catch (e) { this.message = @js(__('That address could not be read.')); return false; }
            el.dispatchEvent(new Event('input', { bubbles: true }));
            return true;
        },
        add() {
            const ok = this.write(u => {
                for (const k of Object.keys(this.f)) {
                    const v = this.f[k].trim();
                    if (v) { u.searchParams.set('utm_' + k, v); } else { u.searchParams.delete('utm_' + k); }
                }
            });
            if (ok) {
                const complete = this.f.source.trim() && this.f.medium.trim() && this.f.campaign.trim();
                this.message = complete ? @js(__('Added to the address.')) : @js(__('Added. Most analytics tools also want a source, a medium and a campaign.'));
            }
        },
        clear() {
            if (this.write(u => { for (const k of Object.keys(this.f)) { u.searchParams.delete('utm_' + k); } })) {
                for (const k of Object.keys(this.f)) { this.f[k] = ''; }
                this.preset = '';
                this.message = @js(__('Tags removed.'));
            }
        },
    }"
    {{ $attributes->class(['rounded-xl border border-slate-500/20 p-3']) }}
>
    <button type="button" x-on:click="toggle()" x-bind:aria-expanded="open" class="flex w-full items-center justify-between gap-2 text-left text-sm font-medium text-ink">
        <span>{{ __('Campaign tags (UTM)') }}</span>
        <span class="text-xs text-muted" x-text="open ? @js(__('Hide')) : @js(__('Show'))">{{ __('Show') }}</span>
    </button>
    <div x-show="open" x-cloak class="mt-3 space-y-3">
        <p class="text-xs text-muted">{{ __('Add tags to the destination so your analytics can tell where visitors came from. Nothing is saved except the address itself.') }}</p>
        <div>
            <label class="label">{{ __('Preset') }}</label>
            <select x-model="preset" x-on:change="usePreset()" class="input">
                <option value="">{{ __('Choose a preset') }}</option>
                <template x-for="p in presets" :key="p.key"><option :value="p.key" x-text="p.label"></option></template>
            </select>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="label">utm_source</label>
                <input type="text" x-model="f.source" maxlength="100" placeholder="newsletter" class="input">
            </div>
            <div>
                <label class="label">utm_medium</label>
                <input type="text" x-model="f.medium" maxlength="100" placeholder="email" class="input">
            </div>
            <div>
                <label class="label">utm_campaign</label>
                <input type="text" x-model="f.campaign" maxlength="100" placeholder="spring-sale" class="input">
            </div>
            <div>
                <label class="label">utm_term <span class="font-normal">({{ __('optional') }})</span></label>
                <input type="text" x-model="f.term" maxlength="100" class="input">
            </div>
            <div class="sm:col-span-2">
                <label class="label">utm_content <span class="font-normal">({{ __('optional') }})</span></label>
                <input type="text" x-model="f.content" maxlength="100" class="input">
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" x-on:click="add()" class="btn-soft btn-sm">{{ __('Add to address') }}</button>
            <button type="button" x-on:click="clear()" class="btn-ghost btn-sm">{{ __('Remove tags') }}</button>
            <span x-show="message" x-text="message" role="status" class="text-xs text-muted"></span>
        </div>
    </div>
</div>
