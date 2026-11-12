{{-- The guest QR page: Wi-Fi, contact card and link/text codes drawn in the browser. No form, no request: what is typed never reaches the server. --}}
@php
    $s = \App\Support\QrStrings::all();
    $demoUrl = public_url('l/menu26').'?src=qr';
    // Title and description say what the page does (search results), from texts the page already shows.
    $qrTitle = $s['h1a'].' '.$s['h1b'];
@endphp
<x-marketing-layout :themed="true" :title="$qrTitle.' · LinkHop'" :description="$s['lead']">
    @push('head')
        <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! \App\Support\Seo::json(\App\Support\Seo::webApplication($qrTitle.' · LinkHop', $s['lead'], \App\Support\Seo::url('qr'))) !!}</script>
        @vite(['resources/css/qr.css', 'resources/js/qr/studio.js'])
        <script nonce="{{ Vite::cspNonce() }}" defer src="{{ asset('vendor/qr-code-styling/qr-code-styling.js') }}?v={{ substr((string) @md5_file(public_path('vendor/qr-code-styling/qr-code-styling.js')), 0, 8) }}"></script>
        <script type="application/json" id="qr-i18n">@json($s)</script>
    @endpush
    <div class="qrs wrap">
        <section class="hero">
          <h1><span>{{ $s['h1a'] }}</span> <em>{{ $s['h1b'] }}</em></h1>
          <p>{{ $s['lead'] }}</p>
          <span class="pill-ok"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span>{{ $s['pill'] }}</span></span>
        </section>
        
        <div class="main">
          <div class="stack">
            <!-- Content -->
            <section class="card" aria-labelledby="content-h">
              <h2 id="content-h" class="sr">{{ $s['content_h'] }}</h2>
              <div class="tabs" role="tablist" aria-label="{{ $s['qr_type'] }}">
                <button class="tab" role="tab" id="tab-wifi" aria-selected="true" aria-controls="panel-wifi" data-tab="wifi">{{ $s['tab_wifi'] }}</button>
                <button class="tab" role="tab" id="tab-vcard" aria-selected="false" aria-controls="panel-vcard" data-tab="vcard" tabindex="-1">{{ $s['tab_vcard'] }}</button>
                <button class="tab" role="tab" id="tab-text" aria-selected="false" aria-controls="panel-text" data-tab="text" tabindex="-1">{{ $s['tab_text'] }}</button>
              </div>
        
              <!-- Wi-Fi -->
              <div class="panel" id="panel-wifi" role="tabpanel" aria-labelledby="tab-wifi">
                <div class="field"><label class="label" for="w-ssid">{{ $s['ssid'] }}</label>
                  <input class="input" id="w-ssid" autocomplete="off" spellcheck="false" autocapitalize="off" value="Cafe Hop"></div>
                <div class="field"><label class="label" for="w-sec">{{ $s['security'] }}</label>
                  <select class="select" id="w-sec">
                    <option value="WPA">{{ $s['sec_wpa'] }}</option>
                    <option value="WEP">{{ $s['sec_wep'] }}</option>
                    <option value="nopass">{{ $s['sec_none'] }}</option>
                    <option value="SAE">{{ $s['sec_sae'] }}</option>
                  </select></div>
                <div class="field narrow" id="w-pass-field"><label class="label" for="w-pass">{{ $s['password'] }}</label>
                  <input class="input" id="w-pass" autocomplete="off" spellcheck="false" autocapitalize="off" data-lpignore="true" value="hop-wifi-2026"></div>
                <label class="check"><input type="checkbox" id="w-hidden"><span>{{ $s['hidden'] }}</span></label>
                <div class="panel-foot"><button type="button" class="btn-link" id="w-example">{{ $s['example'] }}</button></div>
              </div>
        
              <!-- vCard -->
              <div class="panel" id="panel-vcard" role="tabpanel" aria-labelledby="tab-vcard" hidden>
                <div class="fields two">
                  <div class="field"><label class="label" for="v-first">{{ $s['first'] }}</label><input class="input" id="v-first" autocomplete="off" value="Dewi"></div>
                  <div class="field"><label class="label" for="v-last">{{ $s['last'] }}</label><input class="input" id="v-last" autocomplete="off" value="Lestari"></div>
                  <div class="field"><label class="label" for="v-org">{{ $s['org'] }}</label><input class="input" id="v-org" autocomplete="off" value="Kopi Hop"></div>
                  <div class="field"><label class="label" for="v-title">{{ $s['jobtitle'] }}</label><input class="input" id="v-title" autocomplete="off" value="Owner"></div>
                  <div class="field"><label class="label" for="v-mobile">{{ $s['mobile'] }}</label><input class="input" id="v-mobile" type="tel" autocomplete="off" value="+62 812 0000 0000"></div>
                  <div class="field"><label class="label" for="v-work">{{ $s['work'] }}</label><input class="input" id="v-work" type="tel" autocomplete="off"></div>
                  <div class="field"><label class="label" for="v-email">{{ $s['email'] }}</label><input class="input" id="v-email" type="email" autocomplete="off" value="dewi@example.com"></div>
                  <div class="field"><label class="label" for="v-url">{{ $s['website'] }}</label><input class="input" id="v-url" autocomplete="off" value="example.com/menu"></div>
                </div>
                <details class="more">
                  <summary>{{ $s['more'] }}</summary>
                  <div class="fields two">
                    <div class="field"><label class="label" for="v-street">{{ $s['street'] }}</label><input class="input" id="v-street" autocomplete="off"></div>
                    <div class="field"><label class="label" for="v-city">{{ $s['city'] }}</label><input class="input" id="v-city" autocomplete="off"></div>
                    <div class="field"><label class="label" for="v-region">{{ $s['region'] }}</label><input class="input" id="v-region" autocomplete="off"></div>
                    <div class="field"><label class="label" for="v-postal">{{ $s['postal'] }}</label><input class="input" id="v-postal" autocomplete="off"></div>
                    <div class="field"><label class="label" for="v-country">{{ $s['country'] }}</label><input class="input" id="v-country" autocomplete="off"></div>
                    <div class="field" style="grid-column: 1 / -1"><label class="label" for="v-note">{{ $s['note'] }}</label><textarea class="textarea" id="v-note" style="min-height:72px"></textarea></div>
                  </div>
                </details>
                <div class="panel-foot"><button type="button" class="btn-link" id="v-example">{{ $s['example'] }}</button></div>
              </div>
        
              <!-- Link or text -->
              <div class="panel" id="panel-text" role="tabpanel" aria-labelledby="tab-text" hidden>
                <div class="field fill"><label class="label" for="t-text">{{ $s['text_label'] }}</label>
                  <textarea class="textarea" id="t-text" spellcheck="false" style="min-height:132px">https://link.hel.ink</textarea>
                  <p class="help"><span id="t-count" class="tnum">0</span> / <span class="tnum">1000</span> <span>{{ $s['bytes'] }}</span></p></div>
                <p class="help" style="margin-top:0"><span>{{ $s['text_dyn'] }}</span> <a href="#dynamic" class="btn-link">{{ $s['text_dyn_link'] }}</a></p>
              </div>
            </section>
        
            <!-- Style -->
            <section class="card" aria-labelledby="style-h">
              <h2 id="style-h">{{ $s['style_h'] }}</h2>
              <p class="lead">{{ $s['style_lead'] }}</p>
              <div class="style-grid">
                <div>
                  <span class="label" id="fg-l">{{ $s['fg'] }}</span>
                  <div class="colour-row"><input type="color" id="s-fg" value="#000000" aria-labelledby="fg-l"><input class="input mono" id="s-fg-hex" value="#000000" maxlength="7" spellcheck="false" aria-label="{{ $s['fg_hex_l'] }}"></div>
                </div>
                <div>
                  <span class="label" id="bg-l">{{ $s['bg'] }}</span>
                  <div class="colour-row"><input type="color" id="s-bg" value="#ffffff" aria-labelledby="bg-l"><input class="input mono" id="s-bg-hex" value="#ffffff" maxlength="7" spellcheck="false" aria-label="{{ $s['bg_hex_l'] }}"></div>
                </div>
                <div class="swatches" id="presets" style="grid-column:1/-1" role="group" aria-label="{{ $s['presets_l'] }}"></div>
                <div class="field"><label class="label" for="s-dots">{{ $s['dots'] }}</label>
                  <select class="select" id="s-dots">
                    <option value="square">{{ $s['d_square'] }}</option><option value="rounded">{{ $s['d_rounded'] }}</option>
                    <option value="extra-rounded">{{ $s['d_xr'] }}</option><option value="dots">{{ $s['d_dots'] }}</option>
                    <option value="classy">{{ $s['d_classy'] }}</option><option value="classy-rounded">{{ $s['d_classyr'] }}</option>
                  </select></div>
                <div class="field"><label class="label" for="s-corners">{{ $s['corners'] }}</label>
                  <select class="select" id="s-corners">
                    <option value="square">{{ $s['d_square'] }}</option><option value="extra-rounded">{{ $s['d_xr'] }}</option><option value="dot">{{ $s['c_dot'] }}</option>
                  </select></div>
                <div style="grid-column:1/-1">
                  <span class="label">{{ $s['logo'] }}</span>
                  <div class="logo-row">
                    <img class="logo-thumb" id="logo-thumb" alt="" hidden>
                    <button type="button" class="btn btn-outline" id="logo-add">{{ $s['logo_add'] }}</button>
                    <button type="button" class="btn btn-soft" id="logo-del" hidden>{{ $s['logo_del'] }}</button>
                    <input type="file" id="logo-file" accept="image/png,image/jpeg,image/webp,image/svg+xml" hidden>
                    <label class="check" id="logo-size-wrap" hidden style="gap:10px"><span class="muted">{{ $s['logo_size'] }}</span><input type="range" id="logo-size" min="10" max="40" value="22"><span class="tnum mono" id="logo-size-v" style="font-size:12px;min-width:3ch">22%</span></label>
                  </div>
                  <p class="help">{{ $s['logo_help'] }}</p>
                </div>
              </div>
              <div class="panel-foot" style="margin-top:16px"><button type="button" class="btn btn-soft btn-dense" id="style-reset">{{ $s['reset'] }}</button></div>
            </section>
          </div>
        
          <!-- Result -->
          <aside class="sticky" aria-label="{{ $s['result_h'] }}">
            <section class="card">
              <h2 style="margin-bottom:14px">{{ $s['result_h'] }}</h2>
              <div class="qr-box" id="qr-box" aria-live="polite"><div class="qr-empty" id="qr-empty" hidden></div><div id="qr-out" style="width:100%"></div></div>
              <div class="qr-meta" id="qr-meta"></div>
              <div class="qr-actions">
                <button type="button" class="btn btn-primary" id="dl-png"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>PNG</button>
                <button type="button" class="btn btn-outline" id="dl-svg">SVG</button>
              </div>
              <details class="more" style="margin-top:16px">
                <summary>{{ $s['payload_show'] }}</summary>
                <pre class="payload" id="payload" tabindex="0"></pre>
              </details>
            </section>
            <section class="card" aria-labelledby="scan-h">
              <h2 id="scan-h" style="margin-bottom:12px">{{ $s['scan_h'] }}</h2>
              <ul class="advice" id="advice"></ul>
            </section>
          </aside>
        </div>
        
        <!-- Table card -->
        <section class="card" id="card-sec" style="margin-top:20px" aria-labelledby="card-h">
          <h2 id="card-h">{{ $s['card_h'] }}</h2>
          <p class="lead">{{ $s['card_lead'] }}</p>
          <div class="cardsec">
            <div class="fields">
              <div><span class="label">{{ $s['paper'] }}</span>
                <div class="seg" role="group" aria-label="{{ $s['paper'] }}"><button type="button" data-paper="A6" aria-pressed="true">A6</button><button type="button" data-paper="A5" aria-pressed="false">A5</button></div></div>
              <div class="field"><label class="label" for="c-lang">{{ $s['card_lang'] }}</label><select class="select" id="c-lang"></select></div>
              <div class="field"><label class="label" for="c-title">{{ $s['card_title'] }}</label><input class="input" id="c-title" maxlength="40" autocomplete="off"></div>
              <label class="check"><input type="checkbox" id="c-showpw" checked><span>{{ $s['show_pw'] }}</span></label>
              <label class="check"><input type="checkbox" id="c-footer" checked><span>{{ $s['show_footer'] }}</span></label>
              <div><button type="button" class="btn btn-primary btn-lg" id="print-btn"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 9V4h10v5M7 17H5a1 1 0 0 1-1-1v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a1 1 0 0 1-1 1h-2M7 14h10v6H7z"/></svg><span>{{ $s['print'] }}</span></button>
                <p class="help">{{ $s['print_hint'] }}</p></div>
            </div>
            <div style="min-width:0">
              <div class="stage"><div class="paper" id="paper" data-paper="A6"></div></div>
              <p class="paper-note" id="paper-note"></p>
            </div>
          </div>
        </section>
        
        <div class="pair" id="dynamic">
          <section class="card" aria-labelledby="dyn-h">
            <h2 id="dyn-h">{{ $s['dyn_h'] }}</h2>
            <p class="lead" style="margin-bottom:0">{{ $s['dyn_p1'] }}</p>
            <div class="dyn">
              <div class="dyn-qr" id="dyn-qr" data-url="{{ $demoUrl }}" role="img" aria-label="{{ $s['demo_l'] }}"></div>
              <div>
                <span class="label" style="margin-bottom:0">{{ $s['dyn_where'] }}</span>
                <div class="dest" id="dest" role="group"></div>
                <p class="dyn-line"><span class="muted">{{ $s['dyn_opens'] }}</span><br><span class="mono" id="dest-url"></span></p>
              </div>
            </div>
            <ul class="bullets">
              <li>{{ $s['dyn_b1'] }}</li>
              <li>{{ $s['dyn_b2'] }}</li>
            </ul>
            <div class="panel-foot" style="margin-top:16px"><a class="btn btn-outline" href="{{ auth()->check() ? route('links.create') : route('home') }}">{{ $s['dyn_cta'] }}</a></div>
          </section>
          <section class="card" aria-labelledby="priv-h">
            <h2 id="priv-h">{{ $s['priv_h'] }}</h2>
            <ul class="bullets" style="margin-top:12px">
              <li>{{ $s['priv_1'] }}</li>
              <li>{{ $s['priv_2'] }}</li>
              <li>{{ $s['priv_3'] }}</li>
              <li>{{ $s['priv_4'] }}</li>
            </ul>
          </section>
        </div>
    </div>
</x-marketing-layout>
