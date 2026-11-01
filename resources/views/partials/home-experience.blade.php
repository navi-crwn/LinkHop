{{-- The LinkHop home below the hero: one scroll scene per feature (resources/js/home.js adds the motion).
     Every scene is complete as plain HTML and CSS; without script, or with "reduce motion", nothing is hidden. --}}
@php
    use BaconQrCode\Common\ErrorCorrectionLevel;
    use BaconQrCode\Encoder\Encoder;

    $idleDays = config('shortener.guest_idle_days', 14);
    $maxDays = config('shortener.guest_max_days', 30);
    $sceneCopy = collect(['A bunny has a long link to share', 'Hop to hel.ink…', 'hel.ink makes it short', 'On to the destination', 'Arrived ✓', 'Two bunnies, far apart', 'One sends a link; hel.ink makes it short', 'The other gets :link', 'This link has a password', 'Password :dots', 'Unlocked ✓', 'Back home for the next link', 'Wrong password ✕', 'No hop this time'])->mapWithKeys(fn ($text) => [$text => __($text)])->all();
    // the real QR matrix of the demo link, drawn as cubes (server side, so it shows without script)
    $qrMatrix = Encoder::encode('https://hel.ink/l/launch', ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
    $qrSize = $qrMatrix->getWidth();
    $qrDark = [];
    for ($qy = 0; $qy < $qrSize; $qy++) {
        for ($qx = 0; $qx < $qrSize; $qx++) {
            if ($qrMatrix->get($qx, $qy) === 1) {
                $qrDark[] = [$qx, $qy];
            }
        }
    }
    $langCount = count(\App\Http\Middleware\SetLocale::LOCALES);
@endphp

<div class="lx-marq" data-lx-marq aria-label="{{ __('What you get') }}"><div>@foreach (['Short links', 'Custom slugs', 'QR codes', 'Click limits', 'Routing and A/B tests', 'Analytics without tracking', 'Password and 18+ gate', 'API and webhooks', 'Open source', 'Free forever'] as $t)<span>{{ __($t) }}</span>@endforeach</div></div>

<div class="lx-nums" data-lx-nums>
    <div><b data-count="{{ $langCount }}">{{ $langCount }}</b><span>{{ __('languages') }}</span></div>
    <div><b data-count="0">0</b><span>{{ __('ads and trackers') }}</span></div>
    <div><b data-count="4">4</b><span>{{ __('redirect types') }}</span></div>
    <div><b data-count="100" data-suffix="%">100%</b><span>{{ __('free and open source') }}</span></div>
</div>

{{-- 01 long to short: pinned, the scroll plays the story --}}
<section id="link-tour" class="lx lx-story" aria-labelledby="link-tour-title" data-lx-story>
    <div class="lx-wrap lx-story-grid">
        <div class="lx-story-copy">
            <div class="lx-head"><p class="h-label"><span class="n">01</span> · <span>{{ __('From long to short') }}</span></p><h2 id="link-tour-title"><span>{{ __('Long story.') }} <em>{{ __('Short link.') }}</em></span></h2></div>
            <ol class="lx-steps" data-lx-steps>
                <li><b>{{ __('Paste the long link') }}</b><span>{{ __('Any address, however long. Tracking tags and all.') }}</span></li>
                <li><b>{{ __('Choose a slug') }}</b><span>{{ __('Something people can remember, or let hel.ink pick one.') }}</span></li>
                <li><b>{{ __('Get a short link and a QR code') }}</b><span>{{ __('Ready in a second, with a code for print one click away.') }}</span></li>
                <li><b>{{ __('Share it anywhere') }}</b><span>{{ __('Chats, feeds, email, posters. Change the destination later; the link stays.') }}</span></li>
                <li><b>{{ __('See what works') }}</b><span>{{ __('Clicks, countries and referrers, counted without tracking people.') }}</span></li>
            </ol>
        </div>
        <div class="lx-story-vis" aria-hidden="true">
            <div class="lx-addr"><span class="lx-dot"></span><span class="lx-addr-text" dir="ltr">hel.ink/l/<b data-story-type="launch">launch</b></span><em class="lx-ok" data-story-ok>{{ __('Available') }} ✓</em></div>
            <div class="lx-story-body">
                <div class="lx-longcard"><small>{{ __('Destination') }}</small><p class="lx-long" dir="ltr" data-lx-long>https://example.com/projects/2026/launch-day/press-kit?utm_source=newsletter&amp;utm_medium=email&amp;utm_campaign=spring-2026&amp;ref=footer</p></div>
                <div class="lx-ticket-wrap"><div class="lx-ticket" data-lx-ticket data-lx-result>
                    <small dir="ltr">hel.ink</small>
                    <b dir="ltr">hel.ink/l/<i>launch</i></b>
                    <span class="lx-ticket-note">{{ __('Demo link ready · no link was created') }}</span>
                    <div class="lx-qr-flat" style="--n: {{ $qrSize }}">@foreach ($qrDark as [$qx, $qy])<i style="grid-column: {{ $qx + 1 }}; grid-row: {{ $qy + 1 }}"></i>@endforeach</div>
                </div></div>
                <span class="lx-chip c1">WhatsApp</span><span class="lx-chip c2">Telegram</span><span class="lx-chip c3">{{ __('Email') }}</span><span class="lx-chip c4">Instagram</span>
                <div class="lx-stats" data-story-stats><small>{{ __('Clicks') }}</small><b data-story-views>1,284</b><div class="lx-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></div>
            </div>
        </div>
    </div>
</section>

{{-- 02 the journey: the shared 3D scene (resources/js/hop-hero.js), full size --}}
<section id="journey" class="lx lx-journey link-expanded-world" data-hop-demo aria-labelledby="journey-title">
    <div class="lx-wrap">
        <div class="lx-head"><p class="h-label"><span class="n">02</span> · <span>{{ __('Link journey') }}</span></p><h2 id="journey-title"><span>{{ __('How a short link') }} <em>{{ __('travels.') }}</em></span></h2><p class="h-sub"><span>{{ __('A bunny takes your link from here to anywhere. Pick a story and watch.') }}</span></p></div>
        <div class="lx-journey-stage" data-hop-scene data-copy="{{ json_encode($sceneCopy) }}" data-rotate-label="{{ __('Rotate scene') }}">
            <div class="lx-journey-bar">
                <div class="lx-pills" role="group" aria-label="{{ __('Play a scenario') }}">@foreach (['hop' => 'Hop to a link', 'share' => 'Share a link', 'pass-ok' => 'Password: right', 'pass-fail' => 'Password: wrong'] as $key => $label)<button type="button" data-hop-play="{{ $key }}" aria-pressed="false">{{ __($label) }}</button>@endforeach</div>
                <p class="lx-journey-story" data-hop-story aria-live="polite"></p>
            </div>
            <div class="lx-journey-canvas" data-hop-canvas><img data-hop-fallback src="{{ route('brand.asset', 'linkhop-mark-gradient.svg') }}" alt="" width="160" height="160"></div>
        </div>
    </div>
</section>

{{-- 03 a link that looks right where it lands --}}
<section id="share" class="lx" aria-labelledby="share-title" data-lx-share>
    <div class="lx-wrap lx-two">
        <div class="lx-head"><p class="h-label"><span class="n">03</span> · <span>{{ __('Preview') }}</span></p><h2 id="share-title"><span>{{ __('Looks right') }} <em>{{ __('wherever it lands.') }}</em></span></h2><p class="h-sub"><span>{{ __('Give a link its own title, description and picture, so it looks the way you want in chat apps and feeds.') }}</span></p>
            <div class="lx-edit">
                <label class="lx-lab" for="share-title-in">{{ __('Title') }}</label>
                <input id="share-title-in" type="text" value="{{ __('Spring launch') }}" maxlength="60" class="lx-in" data-lx-title>
                <label class="lx-lab" for="share-desc-in">{{ __('Description') }}</label>
                <input id="share-desc-in" type="text" value="{{ __('Everything about the new collection, in one place.') }}" maxlength="110" class="lx-in" data-lx-desc>
                <div class="lx-tones" role="group" aria-label="{{ __('Picture') }}" data-lx-tones>@foreach (['linear-gradient(135deg,#22d3ee,#a78bfa 55%,#f472b6)', 'linear-gradient(135deg,#fde68a,#f97316 60%,#be123c)', 'linear-gradient(135deg,#bbf7d0,#10b981 55%,#0e7490)'] as $i => $tone)<button type="button" style="background: {{ $tone }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" data-tone="{{ $tone }}" aria-label="{{ __('Picture') }} {{ $i + 1 }}"></button>@endforeach</div>
                <p class="h-small">{{ __('Demo only. No link was changed.') }}</p>
            </div>
        </div>
        <div class="lx-chats" aria-live="polite">
            <article class="lx-chat lx-chat-a" data-lx-chat><header>{{ __('Chat') }}</header>
                <p class="lx-msg">{{ __('Look what we made') }}</p>
                <div class="lx-card"><div class="lx-card-img" data-lx-card-img></div><div class="lx-card-text"><small dir="ltr">hel.ink</small><b data-lx-card-title>{{ __('Spring launch') }}</b><p data-lx-card-desc>{{ __('Everything about the new collection, in one place.') }}</p></div></div>
                <p class="lx-msg me" dir="ltr">hel.ink/l/launch</p>
            </article>
            <article class="lx-chat lx-chat-b" data-lx-chat><header>{{ __('Feed') }}</header>
                <div class="lx-feed-top"><span class="lx-av">H</span><b>helena</b><small>{{ __('just now') }}</small></div>
                <div class="lx-card big"><div class="lx-card-img" data-lx-card-img></div><div class="lx-card-text"><small dir="ltr">hel.ink</small><b data-lx-card-title>{{ __('Spring launch') }}</b><p data-lx-card-desc>{{ __('Everything about the new collection, in one place.') }}</p></div></div>
            </article>
        </div>
    </div>
</section>

{{-- 04 QR: the real code, as cubes --}}
<section id="qr" class="lx" aria-labelledby="qr-title" data-lx-qr>
    <div class="lx-wrap lx-two rev">
        <div class="lx-head"><p class="h-label"><span class="n">04</span> · <span>QR</span></p><h2 id="qr-title"><span>{{ __('From the screen') }} <em>{{ __('to the real world.') }}</em></span></h2><p class="h-sub"><span>{{ __('Turn a link into a QR code for a table, poster or package.') }}</span></p>
            <ul class="lx-facts"><li><b>PNG · SVG</b> <span>{{ __('Print-ready, any size.') }}</span></li><li><b>{{ __('Your colours') }}</b> <span>{{ __('Match the poster, add your logo in the middle.') }}</span></li><li><b>{{ __('Scans are counted') }}</b> <span>{{ __('A scan is told apart from a click, in the analytics of the link.') }}</span></li></ul>
            <div class="lx-tones lx-qr-tones" role="group" aria-label="{{ __('Your colours') }}" data-lx-qr-tones>@foreach ([['0b1020', 'ffffff'], ['0891b2', 'ecfeff'], ['6366f1', 'eef2ff'], ['c026d3', 'fdf4ff'], ['ffffff', '1e1b4b']] as $i => [$fg, $bg])<button type="button" style="background: linear-gradient(135deg, #{{ $bg }} 50%, #{{ $fg }} 50%)" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" data-fg="#{{ $fg }}" data-bg="#{{ $bg }}" aria-label="#{{ $fg }}"></button>@endforeach</div>
        </div>
        <div class="lx-qr-stage">
            <div class="lx-vox" data-lx-vox style="--n: {{ $qrSize }}" role="img" aria-label="{{ __('QR code of the demo link') }}"><div class="lx-vox-grid">@foreach ($qrDark as [$qx, $qy])<i style="grid-column: {{ $qx + 1 }}; grid-row: {{ $qy + 1 }}; --k: {{ 0.55 + (($qx * 7 + $qy * 13) % 5) / 11 }}"><b class="top"></b><b class="front"></b><b class="side"></b></i>@endforeach</div></div>
            <p class="lx-iso-label" dir="ltr">hel.ink/l/launch</p>
            <div class="lx-qr-real" x-data="{ i: 0, pairs: [['000000','ffffff'],['0891b2','ecfeff'],['6366f1','eef2ff'],['c026d3','fdf4ff'],['ffffff','1e1b4b']] }" @lx-qr-tone.window="i = $event.detail"><img :src="'{{ route('api.qr.generate') }}?size=120&color=' + pairs[i][0] + '&bgcolor=' + pairs[i][1] + '&data=' + encodeURIComponent('https://hel.ink/l/launch')" src="{{ route('api.qr.generate', ['size' => 120, 'data' => 'https://hel.ink/l/launch']) }}" alt="{{ __('QR code of the demo link') }}" width="84" height="84" loading="lazy"><span>{{ __('Made by hel.ink’s QR generator, the same one your links use.') }}</span></div>
        </div>
    </div>
</section>

{{-- 05 analytics --}}
<section id="analytics" class="lx" aria-labelledby="analytics-title" data-lx-analytics>
    <div class="lx-wrap lx-two">
        <div class="lx-head"><p class="h-label"><span class="n">05</span> · <span>{{ __('Understand') }}</span></p><h2 id="analytics-title"><span>{{ __('Every click') }} <em>{{ __('has a story.') }}</em></span></h2><p class="h-sub"><span>{{ __('See countries, devices and referrers. Compare your links and learn what people connect with, without storing their IP addresses.') }}</span></p>
            <ul class="lx-facts"><li><b>{{ __('Unique visitors.') }}</b> <span>{{ __('Counted once a day with a keyed hash. Never the address itself.') }}</span></li><li><b>{{ __('QR scans and in-app browsers.') }}</b> <span>{{ __('See which channel brings people in.') }}</span></li><li><b>{{ __('Bots stay out.') }}</b> <span>{{ __('Link previews and crawlers are not counted.') }}</span></li></ul></div>
        <div class="lx-analytics-vis">
            <div class="lx-iso" data-lx-iso role="img" aria-label="{{ __('Clicks per day, last four weeks') }}"><div class="lx-iso-grid">@foreach ([3,5,4,7,6,9,8, 4,6,5,8,7,10,9, 5,4,7,9,8,12,10, 6,8,7,11,9,14,12] as $n)<i style="--h: {{ round($n / 14, 2) }}"><b class="top"></b><b class="front"></b><b class="side"></b></i>@endforeach</div><p class="lx-iso-label">{{ __('Clicks per day, last four weeks') }}</p></div>
            <article class="lx-win" aria-label="{{ __('Sample analytics') }}">
                <div class="lx-bar"><span aria-hidden="true">● ● ●</span><span dir="ltr">LinkHop / {{ __('Analytics') }}</span><span>{{ __('Sample') }}</span></div>
                <div class="lx-kpis"><div><small>{{ __('Total clicks') }}</small><b data-metric="1284">1,284</b><em class="lx-up">+34%</em></div><div><small>{{ __('Unique visitors') }}</small><b data-metric="902">902</b></div><div><small>{{ __('QR scans') }}</small><b data-metric="18" data-suffix="%">18%</b></div></div>
                <svg class="lx-line" viewBox="0 0 400 120" preserveAspectRatio="none" role="img" aria-label="{{ __('Clicks per day, last seven days') }}"><defs><linearGradient id="lx-g" x1="0" x2="1"><stop offset="0" stop-color="#22d3ee"/><stop offset=".5" stop-color="#6366f1"/><stop offset="1" stop-color="#f472b6"/></linearGradient></defs><path class="area" d="M0 98 C30 90 50 70 80 76 S130 104 160 70 S220 20 250 44 S310 90 340 50 S380 14 400 22 L400 120 L0 120 Z"/><path class="draw" d="M0 98 C30 90 50 70 80 76 S130 104 160 70 S220 20 250 44 S310 90 340 50 S380 14 400 22" stroke="url(#lx-g)"/></svg>
                <div class="lx-split">
                    <ul class="lx-top" aria-label="{{ __('Top locations') }}">@foreach ([['ID', 46], ['SG', 14], ['MY', 11], ['US', 9]] as [$c, $v])<li><span dir="ltr">{{ $c }}</span><i style="--w: {{ $v * 2 }}%"></i><em>{{ $v }}%</em></li>@endforeach</ul>
                    <div class="lx-heat" aria-hidden="true">@for ($d = 0; $d < 7; $d++)@for ($h = 0; $h < 24; $h++)<i style="--a: {{ round(0.08 + 0.92 * ((($d * 7 + $h * 5) % 13) / 12) ** 2 * (($h >= 7 && $h <= 22) ? 1 : 0.15), 2) }}"></i>@endfor @endfor</div>
                </div>
                <p class="h-small lx-pad">{{ __('Illustrative analytics · not live visitor data.') }}</p>
            </article>
        </div>
    </div>
</section>

{{-- 06 control the moment: schedule, click limit, expiry, fallback --}}
<section id="control" class="lx" aria-labelledby="control-title" data-lx-control>
    <div class="lx-wrap">
        <div class="lx-head"><p class="h-label"><span class="n">06</span> · <span>{{ __('Timing') }}</span></p><h2 id="control-title"><span>{{ __('Start on time.') }} <em>{{ __('Stop on count.') }}</em></span></h2><p class="h-sub"><span>{{ __('Schedule when a link goes live, cap how many visits it gets, and say where visitors go when it ends.') }}</span></p></div>
        <div class="lx-timeline" aria-hidden="true">
            <ol class="lx-track" data-lx-track>
                <li data-state="0"><b>{{ __('Scheduled') }}</b><span>{{ __('Starts in 2 days') }}</span></li>
                <li data-state="1"><b>{{ __('Live') }}</b><span>{{ __('Counting visits') }}</span></li>
                <li data-state="2"><b>{{ __('Limit reached') }}</b><span>{{ __('The 100th visit') }}</span></li>
                <li data-state="3"><b>{{ __('Fallback') }}</b><span>{{ __('Visitors go on to another page') }}</span></li>
            </ol>
            <div class="lx-ctl-body">
                <div class="lx-gauge"><svg viewBox="0 0 120 120"><circle class="bg" cx="60" cy="60" r="50"/><circle class="fg" data-lx-gauge cx="60" cy="60" r="50" pathLength="100"/></svg><div><b data-lx-count>64</b><small>/ 100</small></div></div>
                <div class="lx-ctl-card"><span class="lx-state" data-lx-state>{{ __('Live') }}</span><b dir="ltr">hel.ink/l/promo</b><small dir="ltr" data-lx-dest data-d0="{{ __('Not live yet') }}" data-d1="→ example.com/promo" data-d2="{{ __('This link has ended') }}" data-d3="→ example.com/sale-over">→ example.com/promo</small></div>
            </div>
        </div>
    </div>
</section>

{{-- 07 routing and A/B --}}
<section id="routing" class="lx" aria-labelledby="routing-title" data-lx-routing>
    <div class="lx-wrap lx-two rev">
        <div class="lx-head"><p class="h-label"><span class="n">07</span> · <span>{{ __('Routing') }}</span></p><h2 id="routing-title"><span>{{ __('One link.') }} <em>{{ __('Many destinations.') }}</em></span></h2><p class="h-sub"><span>{{ __('Send visitors somewhere else by country, language, device or browser, or split them between two pages and see which one wins.') }}</span></p>
            <ul class="lx-facts"><li><b>{{ __('Rules, in order.') }}</b> <span>{{ __('The first rule that fits decides; everyone else goes to the main address.') }}</span></li><li><b>{{ __('A/B tests.') }}</b> <span>{{ __('The same visitor keeps seeing the same page.') }}</span></li></ul></div>
        <div class="lx-net" aria-hidden="true">
            <svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid meet"><g class="links"><path d="M120 60 C170 60 170 150 200 150"/><path d="M120 150 L200 150"/><path d="M120 240 C170 240 170 150 200 150"/><path d="M240 150 C290 150 290 60 330 60"/><path d="M240 150 L330 150"/><path d="M240 150 C290 150 290 240 330 240"/></g><circle class="pkt" r="4"/><circle class="pkt" r="4"/><circle class="pkt" r="4"/></svg>
            <span class="lx-who w1">DE · {{ __('German') }}</span><span class="lx-who w2">{{ __('iPhone') }}</span><span class="lx-who w3">ID · {{ __('Indonesian') }}</span>
            <div class="lx-core"><b dir="ltr">hel.ink/l/shop</b><small>{{ __('Rules') }}</small></div>
            <span class="lx-dest d1" dir="ltr">example.de/shop</span><span class="lx-dest d2" dir="ltr">App Store</span><span class="lx-dest d3" dir="ltr">example.id/toko</span>
            <div class="lx-ab"><small>A/B</small><div class="lx-ab-bar"><i class="a" style="--w: 60%"><b>A 60%</b></i><i class="b" style="--w: 40%"><b>B 40%</b></i></div></div>
        </div>
    </div>
</section>

{{-- 08 protect: password, content warning, a check before you go --}}
<section id="protect" class="lx" aria-labelledby="protect-title" data-lx-gate>
    <div class="lx-wrap">
        <div class="lx-head"><p class="h-label"><span class="n">08</span> · <span>{{ __('Protect') }}</span></p><h2 id="protect-title"><span>{{ __('Shared on') }} <em>{{ __('your terms.') }}</em></span></h2><p class="h-sub"><span>{{ __('Add a password, set an expiry date, choose a content warning, and check where any link goes before you open it.') }}</span></p></div>
        <div class="lx-gates">
            <article class="lx-gate" data-gate="pw"><div class="lx-gate-page" aria-hidden="true"><span></span><span></span><span class="s"></span></div><div class="lx-gate-box" aria-hidden="true"><svg class="lock" viewBox="0 0 48 56"><path class="shackle" d="M14 24V16a10 10 0 0 1 20 0v8" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><rect x="6" y="24" width="36" height="28" rx="8" fill="currentColor"/></svg><div class="lx-pw"><small>{{ __('Password') }}</small><span class="dots" data-gate-dots>••••••••</span></div><span class="lx-go">{{ __('Enter') }}</span></div><h3>{{ __('Password gate') }}</h3><p>{{ __('Share the password only with the people you choose.') }}</p></article>
            <article class="lx-gate" data-gate="age"><div class="lx-gate-page" aria-hidden="true"><span></span><span></span><span class="s"></span></div><div class="lx-gate-box" aria-hidden="true"><b class="age">18+</b><div class="lx-pw"><small>{{ __('Content warning') }}</small><span>{{ __('Are you 18 or older?') }}</span></div><span class="lx-go">{{ __('Yes, continue') }}</span></div><h3>{{ __('Content warning') }}</h3><p>{{ __('Visitors confirm before the page opens.') }}</p></article>
            <article class="lx-gate lx-scan" data-gate="scan"><div class="lx-scan-box" aria-hidden="true"><code dir="ltr">hel.ink/l/launch</code><div class="lx-scanline"></div><div class="lx-scan-res"><span class="ok">✓</span><b dir="ltr">example.com/launch</b><small>{{ __('No warnings · safe to open') }}</small></div></div><h3>{{ __('Check a link') }}</h3><p>{{ __('See the destination and its warnings without opening it.') }} <a href="{{ route('link.check') }}">{{ __('Check a link') }} ↗</a></p></article>
        </div>
    </div>
</section>

{{-- 09 build with it: API, idempotency, webhooks --}}
<section id="api" class="lx" aria-labelledby="api-title" data-lx-build>
    <div class="lx-wrap lx-two">
        <div class="lx-head"><p class="h-label"><span class="n">09</span> · <span>API</span></p><h2 id="api-title"><span>{{ __('One request.') }} <em>{{ __('One short link.') }}</em></span></h2><p class="h-sub"><span>{{ __('Connect the REST API to your tools and ShareX. Safe to retry with an idempotency key, and signed webhooks tell your own system what happened.') }}</span></p><div class="h-cta"><a class="h-pill dark" href="{{ route('api-docs') }}">{{ __('API') }} ↗</a></div></div>
        <div class="lx-dev">
            <div class="h-shell"><div class="h-shell-bar"><i></i><i></i><i></i><span>curl</span></div><div class="h-term"><code id="api-curl">curl -X POST https://link.hel.ink/api/shorten -H "Authorization: Bearer $LINKHOP_TOKEN" -H "Idempotency-Key: 7f3c-launch" -d "target_url=https://example.com/launch"</code><button type="button" data-copy-text data-copy-from="#api-curl">{{ __('Copy') }}</button></div><pre data-out><span>{"short_url": "https://hel.ink/l/k7x2m",</span>
<span> "target_url": "https://example.com/launch",</span>
<span> "clicks": 0}</span>
<span class="ok">✓ 201 Created</span></pre></div>
            <div class="h-shell lx-hook"><div class="h-shell-bar"><i></i><i></i><i></i><span>webhook</span></div><pre data-out><span>POST https://your.site/hooks/linkhop</span>
<span>X-LinkHop-Event: link.limit_reached</span>
<span>X-LinkHop-Signature: sha256=9c1f…e07</span>
<span class="ok">✓ 200 OK · signature checked</span></pre></div>
        </div>
    </div>
</section>

{{-- 10 open source --}}
<section id="open" class="lx" aria-labelledby="open-title" data-lx-install>
    <div class="lx-wrap lx-two rev">
        <div class="lx-head"><p class="h-label"><span class="n">10</span> · <span>MIT</span></p><h2 id="open-title"><span>{{ __('Free & open source') }} <em>{{ __('and yours to host.') }}</em></span></h2><p class="h-sub"><span>{{ __('Built with Laravel, Tailwind and Alpine.js. Use hel.ink or host it yourself.') }}</span></p><div class="h-badges"><a class="h-badge" href="https://github.com/navi-crwn/LinkHop/blob/main/LICENSE"><span>{{ __('License') }}</span><b>MIT</b></a><span class="h-badge"><span>PHP</span><b>8.3+</b></span><span class="h-badge"><span>Laravel</span><b>12</b></span></div><div class="h-cta"><a class="h-pill dark" href="https://github.com/navi-crwn/LinkHop">GitHub ↗</a></div></div>
        <div class="h-shell lx-term"><div class="h-shell-bar"><i></i><i></i><i></i><span>bash</span></div><div class="h-term"><code id="install-sh">git clone https://github.com/navi-crwn/LinkHop.git && cd LinkHop && composer install && npm install && npm run build</code><button type="button" data-copy-text data-copy-from="#install-sh">{{ __('Copy') }}</button></div><pre data-out><span>cp .env.example .env && php artisan key:generate</span>
<span>php artisan migrate && php artisan storage:link</span>
<span class="ok">✓ php artisan serve</span></pre></div>
    </div>
</section>

{{-- 11 questions --}}
<section id="faq" class="lx" aria-labelledby="faq-title">
    <div class="lx-wrap">
        <div class="lx-head"><p class="h-label"><span class="n">11</span> · <span>{{ __('Good questions') }}</span></p><h2 id="faq-title"><span>{{ __('Questions,') }} <em>{{ __('answered.') }}</em></span></h2><p class="h-sub"><span>{{ __('Small URL.') }} {{ __('Serious possibilities.') }}</span></p></div>
        <div class="lx-faq" data-lx-faq>@foreach (['Is it really free?' => 'Yes. There is no paid plan and no locked feature, and the code is MIT licensed.', 'Do I need an account?' => 'Not to shorten a link. A free HOP ID keeps your links, shows their stats, and also signs you in to BioHop, PixelHop and NoteHop.', 'What does a click record?' => 'The time, country and city, device, browser and referrer, plus a keyed hash of the IP address for counting unique visitors. Never the IP address itself.', 'How long do guest links last?' => 'Guest links expire after :idle days without a click or :max days in total. Links made with a HOP ID stay until you delete them.', 'Can I change where a link goes?' => 'Yes. Edit the destination any time; the short link and its stats stay the same.', 'Can I report a link?' => 'Yes. Every hel.ink link has a report option, and the check page shows warnings before you open anything.'] as $question => $answer)<article class="lx-cell lx-q"><h3>{{ __($question) }}</h3><p>{{ __($answer, ['idle' => $idleDays, 'max' => $maxDays]) }}</p></article>@endforeach</div>
    </div>
</section>

{{-- 12 family: every other app as its own 3D mark; the flat mark stays without WebGL --}}
<section id="family" class="lx" aria-labelledby="family-title">
    <div class="lx-wrap">
        <div class="lx-head"><p class="h-label"><span class="n">12</span> · <span>HOP</span></p><h2 id="family-title"><span><em>{{ __('HOP family') }}</em></span></h2><p class="h-sub"><span>{{ __('One free HOP ID signs you in to every app.') }}</span></p></div>
        <div class="lx-family" data-lk-family>@foreach (collect(\Hop\Ui\Assets::family('linkhop'))->reject(fn ($app) => $app['current']) as $app)<article class="lx-fam"><figure class="h-art"><img src="{{ $app['icons']['mark']['url'] }}" alt="" width="120" height="120" loading="lazy"><canvas data-hop-hero="{{ $app['slug'] }}" tabindex="0" aria-label="{{ __('Drag or use arrow keys to rotate') }}"></canvas></figure><b>{{ $app['name'] }}</b><a href="{{ $app['url'] }}">{{ parse_url($app['url'], PHP_URL_HOST) }} ↗</a></article>@endforeach</div>
    </div>
</section>

{{-- 13 the last word --}}
<section class="lx lx-final" aria-labelledby="final-title">
    <div class="lx-wrap"><p class="h-label"><span class="n">13</span> · <span>{{ __('Your next link') }}</span></p><h2 id="final-title"><span>{{ __('Less link.') }} <em>{{ __('More possibility.') }}</em></span></h2><p class="h-sub"><span>{{ __('Shorten. Share. Understand.') }}</span></p><div class="h-cta"><a class="h-pill dark" href="#target_url">{{ __('Shorten') }} ↑</a><a class="h-pill" href="{{ route('register') }}">{{ __('Create free account') }}</a></div></div>
</section>
