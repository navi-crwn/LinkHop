{{-- The dashboard's QR codes page: a list (saved codes, then the codes of the short links) and one "Create QR" form for
     Wi-Fi, contact card and link or text. Codes are drawn in the browser (resources/js/qr-dashboard.js); the server keeps
     the encrypted content of the codes the member chose to save. --}}
@php
    $s = \App\Support\QrStrings::all();
    $typeName = ['wifi' => $s['tab_wifi'], 'vcard' => $s['tab_vcard'], 'text' => $s['tab_text']];
    $texts = $s + [
        'save' => __('Save'),
        'saving_failed' => __('Could not save the QR code (:status).'),
        'download' => __('Download'),
    ];
    $savedData = $saved->map(fn ($q) => ['id' => $q->id, 'type' => $q->type, 'label' => $q->label, 'payload' => $q->payload, 'style' => $q->style])->values();
@endphp
<x-app-layout>
    <x-slot name="pageTitle">{{ __('QR codes') }}</x-slot>
    @vite(['resources/js/qr-dashboard.js'])
    <div class="space-y-4" id="qc-root">
        <h1 class="sr-only">{{ __('QR codes') }}</h1>
        @if (session('status'))
            <div class="glass rounded-2xl px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300" role="status">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between gap-3">
            <h2 class="section-title">{{ __('Saved QR codes') }}</h2>
            <button type="button" class="btn-primary" id="qc-open" aria-controls="qc-creator" aria-expanded="false">{{ __('Create QR') }}</button>
        </div>

        {{-- The creator: hidden until "Create QR" is pressed. Nothing here reaches the server before Save. --}}
        <section class="glass-card" id="qc-creator" hidden aria-labelledby="qc-creator-title">
            <h3 id="qc-creator-title" class="sr-only">{{ __('Create QR') }}</h3>
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
                <div class="min-w-0 space-y-4">
                    <div class="flex flex-wrap gap-0.5" role="tablist" aria-label="{{ $s['qr_type'] }}">
                        @foreach ($typeName as $key => $name)
                            <button type="button" role="tab" class="tab {{ $loop->first ? 'bg-slate-900/5 text-ink dark:bg-white/10' : 'text-muted hover:text-ink' }}" data-qc-tab="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" id="qc-tab-{{ $key }}" aria-controls="qc-panel-{{ $key }}" data-ctl="tab">{{ $name }}</button>
                        @endforeach
                    </div>

                    <div id="qc-panel-wifi" role="tabpanel" aria-labelledby="qc-tab-wifi" class="grid gap-3 sm:grid-cols-2">
                        <div><label class="label" for="qc-w-ssid">{{ $s['ssid'] }}</label><input class="input" id="qc-w-ssid" autocomplete="off" spellcheck="false" autocapitalize="off"></div>
                        <div><label class="label" for="qc-w-sec">{{ $s['security'] }}</label>
                            <select class="input" id="qc-w-sec">
                                <option value="WPA">{{ $s['sec_wpa'] }}</option>
                                <option value="WEP">{{ $s['sec_wep'] }}</option>
                                <option value="nopass">{{ $s['sec_none'] }}</option>
                                <option value="SAE">{{ $s['sec_sae'] }}</option>
                            </select></div>
                        <div id="qc-w-pass-field"><label class="label" for="qc-w-pass">{{ $s['password'] }}</label><input class="input" id="qc-w-pass" autocomplete="off" spellcheck="false" autocapitalize="off" data-lpignore="true"></div>
                        <label class="flex items-center gap-2 text-sm sm:self-end sm:pb-2"><input type="checkbox" id="qc-w-hidden"><span>{{ $s['hidden'] }}</span></label>
                    </div>

                    <div id="qc-panel-vcard" role="tabpanel" aria-labelledby="qc-tab-vcard" class="grid gap-3 sm:grid-cols-2" hidden>
                        <div><label class="label" for="qc-v-first">{{ $s['first'] }}</label><input class="input" id="qc-v-first" autocomplete="off"></div>
                        <div><label class="label" for="qc-v-last">{{ $s['last'] }}</label><input class="input" id="qc-v-last" autocomplete="off"></div>
                        <div><label class="label" for="qc-v-org">{{ $s['org'] }}</label><input class="input" id="qc-v-org" autocomplete="off"></div>
                        <div><label class="label" for="qc-v-title">{{ $s['jobtitle'] }}</label><input class="input" id="qc-v-title" autocomplete="off"></div>
                        <div><label class="label" for="qc-v-mobile">{{ $s['mobile'] }}</label><input class="input" id="qc-v-mobile" type="tel" autocomplete="off"></div>
                        <div><label class="label" for="qc-v-email">{{ $s['email'] }}</label><input class="input" id="qc-v-email" type="email" autocomplete="off"></div>
                        <div class="sm:col-span-2"><label class="label" for="qc-v-url">{{ $s['website'] }}</label><input class="input" id="qc-v-url" autocomplete="off"></div>
                    </div>

                    <div id="qc-panel-text" role="tabpanel" aria-labelledby="qc-tab-text" hidden>
                        <label class="label" for="qc-t-text">{{ $s['text_label'] }}</label>
                        <textarea class="input" id="qc-t-text" rows="3" spellcheck="false" placeholder="https://"></textarea>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-4">
                        <div><label class="label" for="qc-s-fg">{{ $s['fg'] }}</label><input type="color" id="qc-s-fg" value="#000000" class="h-9 w-full cursor-pointer rounded-lg border border-slate-500/20 bg-transparent"></div>
                        <div><label class="label" for="qc-s-bg">{{ $s['bg'] }}</label><input type="color" id="qc-s-bg" value="#ffffff" class="h-9 w-full cursor-pointer rounded-lg border border-slate-500/20 bg-transparent"></div>
                        <div><label class="label" for="qc-s-dots">{{ $s['dots'] }}</label>
                            <select class="input" id="qc-s-dots">
                                <option value="square">{{ $s['d_square'] }}</option><option value="rounded">{{ $s['d_rounded'] }}</option>
                                <option value="extra-rounded">{{ $s['d_xr'] }}</option><option value="dots">{{ $s['d_dots'] }}</option>
                                <option value="classy">{{ $s['d_classy'] }}</option><option value="classy-rounded">{{ $s['d_classyr'] }}</option>
                            </select></div>
                        <div><label class="label" for="qc-s-corners">{{ $s['corners'] }}</label>
                            <select class="input" id="qc-s-corners">
                                <option value="square">{{ $s['d_square'] }}</option><option value="extra-rounded">{{ $s['d_xr'] }}</option><option value="dot">{{ $s['c_dot'] }}</option>
                            </select></div>
                    </div>
                    <div class="max-w-sm"><label class="label" for="qc-label">{{ __('Label') }}</label><input class="input" id="qc-label" maxlength="80" autocomplete="off"></div>
                </div>

                <div class="min-w-0 space-y-3 lg:w-64">
                    <div class="mx-auto flex w-full max-w-60 items-center justify-center rounded-xl border border-slate-500/15 bg-white p-1" style="aspect-ratio: 1 / 1" aria-live="polite">
                        <div id="qc-preview" class="w-full"></div>
                        <p id="qc-empty" class="px-3 text-center text-xs text-slate-500" hidden></p>
                    </div>
                    <p id="qc-error" class="text-sm text-rose-600 dark:text-rose-400" role="alert" hidden></p>
                    <div class="form-actions">
                        <button type="button" class="btn-ghost" id="qc-cancel">{{ __('Cancel') }}</button>
                        <button type="button" class="btn-primary" id="qc-save" disabled>{{ __('Save') }}</button>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-xs text-muted">{{ __('Saved codes are stored encrypted and shown only to you. Nothing is stored until you press Save.') }}</p>
        </section>

        {{-- Saved stand-alone codes --}}
        @if ($saved->isEmpty())
            <div class="glass-card text-sm text-muted" id="qc-empty-saved">{{ __('No saved QR codes yet. Create one for Wi-Fi, a contact card or any text.') }}</div>
        @else
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" id="qc-saved">
                @foreach ($saved as $q)
                    <li class="glass-card flex min-w-0 gap-3" data-qc-item="{{ $q->id }}">
                        <div class="h-24 w-24 shrink-0 overflow-hidden rounded-lg border border-slate-500/15 bg-white p-0.5" data-qc-thumb="{{ $q->id }}" aria-hidden="true"></div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink" title="{{ $q->label }}">{{ $q->label }}</p>
                            <p class="mt-0.5"><span class="chip-gray">{{ $typeName[$q->type] ?? $q->type }}</span></p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <button type="button" class="btn-soft btn-sm" data-qc-view="{{ $q->id }}">{{ __('View') }}</button>
                                <button type="button" class="btn-soft btn-sm" data-qc-dl="png" data-qc-id="{{ $q->id }}">PNG</button>
                                <button type="button" class="btn-soft btn-sm" data-qc-dl="svg" data-qc-id="{{ $q->id }}">SVG</button>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                <details class="min-w-0">
                                    <summary class="btn-ghost btn-sm cursor-pointer list-none">{{ __('Rename') }}</summary>
                                    <form method="POST" action="{{ route('qr-codes.update', $q->id) }}" class="mt-2 flex gap-1.5">
                                        @csrf @method('PATCH')
                                        <input name="label" value="{{ $q->label }}" maxlength="80" required class="input input-dense min-w-0 flex-1" aria-label="{{ __('Label') }}">
                                        <button type="submit" class="btn-primary btn-sm">{{ __('Save') }}</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('qr-codes.destroy', $q->id) }}" data-confirm="{{ __('Delete this QR code?') }}" data-confirm-message="{{ __('Only the saved entry goes; copies you already printed or shared keep working.') }}" data-confirm-danger data-confirm-subject="{{ $q->label }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-ghost btn-sm text-rose-600 dark:text-rose-400">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- The QR codes of the short links: they live with the link (colours, downloads) --}}
        <h2 class="section-title pt-2">{{ __('From your short links') }}</h2>
        @if ($links->isEmpty())
            <div class="glass-card text-sm text-muted">{{ __('No short link has a QR code yet.') }}</div>
        @else
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" id="qc-links">
                @foreach ($links as $link)
                    @php($bg = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $link->qr_bg_color) ? $link->qr_bg_color : '#ffffff')
                    <li class="glass-card flex min-w-0 gap-3">
                        <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-500/15 p-0.5" style="background-color: {{ $bg }}">
                            <img src="{{ route('links.qr.download', [$link, 'png']) }}?v={{ md5((string) $link->updated_at) }}" alt="{{ __('QR code for :url', ['url' => $link->short_url]) }}" width="88" height="88" loading="lazy" class="h-full w-full object-contain">
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink">/{{ $link->slug }}</p>
                            <p class="truncate text-xs text-muted" title="{{ $link->target_url }}">{{ $link->target_url }}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <a href="{{ route('links.show', $link) }}" class="btn-soft btn-sm">{{ __('View') }}</a>
                                <a href="{{ route('links.qr.download', [$link, 'png']) }}" download class="btn-soft btn-sm">PNG</a>
                                <a href="{{ route('links.qr.download', [$link, 'svg']) }}" download class="btn-soft btn-sm">SVG</a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- A larger look at a saved code --}}
        <dialog id="qc-view" class="glass-strong glass-popover no-skin m-auto w-[min(92vw,24rem)] rounded-2xl p-5 text-ink backdrop:bg-slate-950/40" aria-labelledby="qc-view-title">
            <div class="flex items-start justify-between gap-3">
                <h2 id="qc-view-title" class="min-w-0 truncate text-base font-semibold"></h2>
                <button type="button" class="icon-btn" id="qc-view-close" aria-label="{{ __('Close') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="mx-auto mt-4 w-full max-w-72 rounded-xl border border-slate-500/15 bg-white p-1"><div id="qc-view-canvas" class="w-full"></div></div>
            <div class="mt-4 flex justify-center gap-2">
                <button type="button" class="btn-soft btn-sm" id="qc-view-png">PNG</button>
                <button type="button" class="btn-soft btn-sm" id="qc-view-svg">SVG</button>
            </div>
        </dialog>

        <script type="application/json" id="qc-data">@json(['saved' => $savedData, 't' => $texts, 'storeUrl' => route('qr-codes.store')])</script>
    </div>
</x-app-layout>
