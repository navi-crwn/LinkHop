{{-- 2. the rail --}}
<div class="h-rail" data-rv><span>{{ __('Shorten. Share. Understand.') }}</span><span>{{ __('Free & open source') }}</span><span>{{ __('No account needed to start') }}</span><a href="#link-tour">{{ __('Take the interactive tour') }} ↓</a></div>

{{-- 3. a link's journey: pinned, the scroll turns the chapters --}}
<section id="link-tour" class="h-sec" aria-labelledby="link-tour-title">
    <p class="h-label"><span class="n">01</span> · <span>{{ __('A link, from start to everywhere') }}</span></p>
    <h2 id="link-tour-title" data-split>{{ __('Less link.') }} <em>{{ __('More possibility.') }}</em></h2>
    <p class="h-sub" data-rv>{{ __('Scroll through a link’s journey, or choose a chapter. Everything in this demo stays in your browser.') }}</p>
    <div class="h-story" id="link-story" data-scroll-story style="--story-n:3">
        <div class="h-story-pin">
            <div class="h-story-nav">@foreach (['Make it short', 'Share with confidence', 'See the bigger picture'] as $step)<button type="button" data-story-step="{{ $loop->index }}"><span>0{{ $loop->iteration }}</span>{{ __($step) }}</button>@endforeach</div>
            <div class="h-story-progress" aria-hidden="true"><span data-story-progress></span></div>
            <div class="h-story-panel" data-story-panel x-data="{ slug: 'launch', shortened: false }">
                <div class="h-story-copy"><p class="h-label"><span class="n">01</span> · <span>{{ __('Create') }}</span></p><h3>{{ __('Long story.') }} {{ __('Short link.') }}</h3><p>{{ __('Give your next project a link people can remember. Pick a custom slug, keep it organised, and change the destination when you need to.') }}</p><a href="#target_url" class="h-btn">{{ __('Shorten a real link above') }} ↑</a></div>
                <div class="link-demo-window"><div class="showcase-window-bar"><span>● ● ●</span> LinkHop / {{ __('New link') }} <span>{{ __('Demo') }}</span></div><div class="link-demo-content"><label>{{ __('Destination') }}<span class="link-demo-long">https://example.com/projects/2026/launch?source=newsletter</span></label><label for="demo-slug">{{ __('Custom slug') }}</label><div class="link-demo-input"><span>hel.ink/l/</span><input id="demo-slug" type="text" maxlength="32" x-model="slug" @input="shortened = false" aria-label="{{ __('Demo custom slug') }}"></div><button class="showcase-button primary" type="button" @click="shortened = true">{{ __('Try shortening') }} ↗</button><div class="link-demo-result" :class="shortened && 'is-ready'"><span x-text="shortened ? '✓' : '↗'" aria-hidden="true">↗</span><strong x-text="shortened ? 'hel.ink/l/' + (slug.toLowerCase().replace(/[^a-z0-9-]/g, '') || 'launch') : @js(__('Your next great link'))"></strong><small x-text="shortened ? @js(__('Demo link ready · no link was created')) : @js(__('Choose a name. Make it memorable.'))" aria-live="polite"></small></div></div></div>
            </div>
            <div class="h-story-panel" data-story-panel hidden x-data="{ locked: true, password: '', opened: false, attempted: false }">
                <div class="h-story-copy"><p class="h-label"><span class="n">02</span> · <span>{{ __('Protect') }}</span></p><h3>{{ __('The right people.') }} {{ __('The right destination.') }}</h3><p>{{ __('Add a password, set an expiry date, and choose a content warning. Sharing a link does not have to mean sharing it with everyone.') }}</p><p class="h-small">{{ __('Try the demo password: hop') }}</p></div>
                <div class="link-demo-window"><div class="showcase-window-bar"><span>● ● ●</span> hel.ink/l/launch <span>{{ __('Demo') }}</span></div><div class="link-demo-content link-gate-demo"><div class="link-gate-icon" aria-hidden="true" x-text="opened || !locked ? '↗' : '▣'">▣</div><h4 x-text="opened || !locked ? @js(__('You’re on your way.')) : @js(__('This link has a password.'))"></h4><button type="button" class="showcase-button" @click="locked = !locked; opened = false; attempted = false" :aria-pressed="locked">{{ __('Password protection') }}</button><form x-show="locked && !opened" @submit.prevent="opened = password === 'hop'; attempted = true"><label for="demo-password" class="sr-only">{{ __('Demo password') }}</label><input id="demo-password" type="password" x-model="password" placeholder="hop" autocomplete="off"><button type="submit" class="showcase-button primary">{{ __('Unlock preview') }}</button></form><p aria-live="polite" x-text="attempted && !opened ? @js(__('Wrong password. Try hop.')) : opened ? @js(__('Unlocked · this is a local demonstration.')) : @js(__('You control the way in.'))"></p></div></div>
            </div>
            <div class="h-story-panel" data-story-panel hidden x-data="{ period: 7 }">
                <div class="h-story-copy"><p class="h-label"><span class="n">03</span> · <span>{{ __('Understand') }}</span></p><h3>{{ __('Every click') }} {{ __('has a story.') }}</h3><p>{{ __('See countries, devices and referrers. Compare your links and learn what people connect with, without storing their IP addresses.') }}</p><p class="h-small">{{ __('Illustrative analytics · not live visitor data.') }}</p></div>
                <div class="link-demo-window"><div class="showcase-window-bar"><span>● ● ●</span> LinkHop / {{ __('Analytics') }} <span>{{ __('Sample') }}</span></div><div class="link-demo-content"><div class="link-analytics-head"><div><span class="showcase-caption">{{ __('Total clicks') }}</span><p class="showcase-stat" x-text="period === 7 ? '1,284' : '5,640'">1,284</p></div><div class="showcase-actions"><button type="button" class="showcase-button" @click="period = 7" :aria-pressed="period === 7">7d</button><button type="button" class="showcase-button" @click="period = 30" :aria-pressed="period === 30">30d</button></div></div><div class="showcase-chart" aria-hidden="true">@foreach ([25, 38, 32, 58, 45, 70, 63, 84, 72, 96] as $height)<i :style="`height:${period === 7 ? {{ $height }} : {{ 100 - $height / 2 }}}%`"></i>@endforeach</div><div class="link-demo-ranks"><div><span>{{ __('Mobile') }}</span><strong x-text="period === 7 ? '72%' : '68%'">72%</strong></div><div><span>{{ __('Desktop') }}</span><strong x-text="period === 7 ? '28%' : '32%'">28%</strong></div><div><span>{{ __('Top referrer') }}</span><strong>newsletter</strong></div></div></div></div>
            </div>
            <div class="h-story-foot"><span>{{ __('Scroll to explore · buttons work too') }}</span><div><button type="button" data-story-prev aria-label="{{ __('Previous step') }}">←</button><button type="button" data-story-next aria-label="{{ __('Next step') }}">→</button></div></div>
        </div>
    </div>
</section>

{{-- 4. the hop: islands rise, bunnies hop through hel.ink to where a link goes (resources/js/hop-hero.js) --}}
<section class="h-sec link-expanded-world" data-hop-demo aria-labelledby="short-link-demo-heading">
    <p class="h-label"><span class="n">02</span> · <span>{{ __('Link journey') }}</span></p><h2 id="short-link-demo-heading" data-split><em>{{ __('How a short link works') }}</em></h2><p class="h-sub" data-rv>{{ __('A bunny takes your link from here to anywhere.') }}</p>
    <div class="pt-5" data-hop-scene data-copy="{{ json_encode($sceneCopy) }}" data-rotate-label="{{ __('Rotate scene') }}">
        <div class="flex flex-col items-center gap-2 px-2">
            <div class="flex flex-wrap justify-center gap-2" role="group" aria-label="{{ __('Play a scenario') }}">
                @foreach (['hop' => 'Hop to a link', 'share' => 'Share a link', 'pass-ok' => 'Password: right', 'pass-fail' => 'Password: wrong'] as $key => $label)
                    <button type="button" data-hop-play="{{ $key }}" aria-pressed="false" class="hop-pill rounded-full border border-slate-200 bg-white/80 px-3.5 py-1.5 text-xs font-semibold text-slate-600 backdrop-blur transition hover:border-indigo-300 hover:text-indigo-700 sm:text-[13px] dark:border-white/10 dark:bg-slate-900/60 dark:text-white/70 dark:hover:text-white">{{ __($label) }}</button>
                @endforeach
            </div>
            <p class="min-h-5 text-center text-[13px] font-semibold text-slate-500 dark:text-white/60" data-hop-story aria-live="polite"></p>
        </div>
        <div class="relative h-[360px] sm:h-[420px]" data-hop-canvas>
            <img data-hop-fallback src="{{ route('brand.asset', 'linkhop-mark-gradient.svg') }}" alt="" class="absolute inset-0 m-auto h-32 w-auto opacity-80">
        </div>
    </div>
</section>

{{-- 5. QR: a real code from the real generator --}}
<section class="h-sec h-stage" id="qr" aria-labelledby="qr-title">
    <div class="h-stage-copy"><p class="h-label"><span class="n">03</span> · <span>QR</span></p><h2 id="qr-title" data-split>{{ __('From the screen') }} <em>{{ __('to the real world.') }}</em></h2><p class="h-sub" data-rv>{{ __('Turn a link into a QR code for a table, poster or package.') }}</p><ul class="h-feats" data-rv-group><li><span aria-hidden="true">✓</span><span><b>PNG · SVG</b>{{ __('Print-ready, any size.') }}</span></li><li><span aria-hidden="true">✓</span><span><b>{{ __('Your colours') }}</b>{{ __('Match the poster, add your logo in the middle.') }}</span></li><li><span aria-hidden="true">✓</span><span><b>{{ __('Counted like any click') }}</b>{{ __('Scans show up in the analytics of the link.') }}</span></li></ul></div>
    <div class="h-stage-visual h-window" data-rv x-data="{ text: 'https://hel.ink/l/launch', shown: 'https://hel.ink/l/launch' }"><div class="showcase-window-bar"><span>● ● ●</span> LinkHop / QR <span>{{ __('Live') }}</span></div>
        <div class="h-window-body link-qr-demo"><img :src="'{{ route('api.qr.generate') }}?size=220&data=' + encodeURIComponent(shown)" src="{{ route('api.qr.generate', ['size' => 220, 'data' => 'https://hel.ink/l/launch']) }}" alt="{{ __('QR code of the demo link') }}" width="220" height="220" loading="lazy"><label for="qr-text">{{ __('Text in the code') }}<input id="qr-text" type="text" x-model="text" maxlength="120" dir="ltr"></label><button type="button" class="primary" @click="shown = text.trim() || 'https://hel.ink/l/launch'">{{ __('Make the code') }}</button><p class="h-small">{{ __('Made by hel.ink’s QR generator, the same one your links use.') }}</p></div></div>
</section>

{{-- 6. check a link --}}
<section class="h-sec h-stage" id="check" aria-labelledby="check-title">
    <div class="h-stage-copy"><p class="h-label"><span class="n">04</span> · <span>{{ __('Check a link') }}</span></p><h2 id="check-title" data-split>{{ __('Know where it goes') }} <em>{{ __('before you go.') }}</em></h2><p class="h-sub" data-rv>{{ __('Paste any hel.ink link and see its destination, its safety status and its warnings, without opening it.') }}</p><div class="h-cta"><a class="h-pill dark" href="{{ route('link.check') }}">{{ __('Check a link') }} ↗</a></div></div>
    <div class="h-stage-visual h-window" data-rv x-data="{ q: 'hel.ink/l/launch', checked: false }"><div class="showcase-window-bar"><span>● ● ●</span> LinkHop / {{ __('Check a link') }} <span>{{ __('Demo') }}</span></div>
        <div class="h-window-body"><label for="check-demo">hel.ink/l/…<input id="check-demo" type="text" x-model="q" @input="checked = false" dir="ltr"></label><button type="button" class="primary" @click="checked = true">{{ __('Check') }}</button><div class="h-result" :class="checked && 'is-ready'"><span aria-hidden="true" x-text="checked ? '✓' : '?'">?</span><strong x-text="checked ? 'https://example.com/projects/2026/launch' : @js(__('Destination'))"></strong><small x-text="checked ? @js(__('No warnings · safe to open · created by a HOP ID account')) : @js(__('The destination appears here, never opened.'))" aria-live="polite"></small></div><p class="h-small">{{ __('Demo only. The real check uses the live link database.') }}</p></div></div>
</section>

{{-- 7. protect: expiry and content warning --}}
<section class="h-sec h-stage" id="protect" aria-labelledby="protect-title">
    <div class="h-stage-copy"><p class="h-label"><span class="n">05</span> · <span>{{ __('Protect') }}</span></p><h2 id="protect-title" data-split>{{ __('Shared on') }} <em>{{ __('your terms.') }}</em></h2><p class="h-sub" data-rv>{{ __('Add a password, set an expiry date, and choose a content warning. Sharing a link does not have to mean sharing it with everyone.') }}</p></div>
    <div class="h-stage-visual h-window" data-rv x-data="{ days: '7', warn: 'none' }"><div class="showcase-window-bar"><span>● ● ●</span> hel.ink/l/launch <span>{{ __('Demo') }}</span></div>
        <div class="h-window-body"><label for="exp-demo">{{ __('Expires') }}<select id="exp-demo" x-model="days"><option value="">{{ __('Never') }}</option><option value="1">{{ __('In 1 day') }}</option><option value="7">{{ __('In 7 days') }}</option><option value="30">{{ __('In 30 days') }}</option></select></label><label for="warn-demo">{{ __('Content warning') }}<select id="warn-demo" x-model="warn"><option value="none">{{ __('None') }}</option><option value="sensitive">{{ __('Sensitive content') }}</option><option value="adult">{{ __('Adult 18+') }}</option></select></label><div class="h-result"><span aria-hidden="true" x-text="warn === 'none' ? '↗' : '▣'">↗</span><strong x-text="days ? @js(__('Expires in :n days')).replace(':n', days) : @js(__('Never expires'))"></strong><small x-text="warn === 'adult' ? @js(__('Visitors confirm they are 18+ before the page opens.')) : warn === 'sensitive' ? @js(__('Visitors see a warning before the page opens.')) : @js(__('Opens straight away.'))"></small></div><p class="h-small">{{ __('Demo only. No link was changed.') }}</p></div></div>
</section>

{{-- 8. organise --}}
<section class="h-sec h-stage" id="organise" aria-labelledby="organise-title">
    <div class="h-stage-copy"><p class="h-label"><span class="n">06</span> · <span>{{ __('Organise') }}</span></p><h2 id="organise-title" data-split>{{ __('Every link') }} <em>{{ __('in its place.') }}</em></h2><p class="h-sub" data-rv>{{ __('Tags, search and a dashboard that shows what is working. Change a destination later; the short link stays the same.') }}</p></div>
    <div class="h-stage-visual h-window" data-rv x-data="{ tag: '' }"><div class="showcase-window-bar"><span>● ● ●</span> LinkHop / {{ __('Links') }} <span>{{ __('Demo') }}</span></div>
        <div class="h-window-body"><div class="h-tabs" role="group" aria-label="{{ __('Filter by tag') }}"><button type="button" @click="tag = ''" :aria-pressed="tag === ''">{{ __('All') }}</button>@foreach (['launch', 'menu', 'docs'] as $t)<button type="button" @click="tag = '{{ $t }}'" :aria-pressed="tag === '{{ $t }}'">#{{ $t }}</button>@endforeach</div><div class="link-demo-ranks">@foreach ([['launch', 'hel.ink/l/launch', '1,284'], ['launch', 'hel.ink/l/beta', '412'], ['menu', 'hel.ink/l/menu', '96'], ['docs', 'hel.ink/l/api', '2,031']] as [$t, $url, $n])<div x-show="!tag || tag === '{{ $t }}'"><span dir="ltr">{{ $url }} <em class="not-italic text-[10px]">#{{ $t }}</em></span><strong>{{ $n }}</strong></div>@endforeach</div><p class="h-small">{{ __('Sample links · not your data.') }}</p></div></div>
</section>

{{-- 9. API and ShareX --}}
<section class="h-sec h-stage" id="api" aria-labelledby="api-title">
    <div class="h-stage-copy"><p class="h-label"><span class="n">07</span> · <span>API</span></p><h2 id="api-title" data-split>{{ __('One request.') }} <em>{{ __('One short link.') }}</em></h2><p class="h-sub" data-rv>{{ __('Connect the REST API to your tools and ShareX.') }}</p><div class="h-cta"><a class="h-pill dark" href="{{ route('api-docs') }}">{{ __('API') }} ↗</a></div></div>
    <div class="h-stage-visual h-shell" data-rv><div class="h-shell-bar"><i></i><i></i><i></i><span>curl</span></div><div class="h-term"><code id="api-curl">curl -X POST https://link.hel.ink/api/links -H "Authorization: Bearer $LINKHOP_TOKEN" -d "target_url=https://example.com/launch"</code><button type="button" data-copy-text data-copy-from="#api-curl">{{ __('Copy') }}</button></div><pre>{"short_url": "https://hel.ink/l/k7x2m",
 "target_url": "https://example.com/launch",
 "clicks": 0}
<span class="ok">✓ 201 Created</span></pre></div>
</section>

{{-- 10. tutorial --}}
<section class="h-sec" id="tutorial" aria-labelledby="tutorial-title">
    <p class="h-label"><span class="n">08</span> · <span>{{ __('Tutorial') }}</span></p><h2 id="tutorial-title" data-split>{{ __('Three steps.') }} <em>{{ __('No account needed.') }}</em></h2><p class="h-sub" data-rv>{{ __('Shorten. Share. Understand.') }}</p>
    <div class="h-tut" data-rv-group><article><h3>{{ __('Paste the long link') }}</h3><p>{{ __('Drop it in the box at the top. Open Advanced for a custom slug or a content warning.') }}</p><div class="h-tut-art">https://example.com/projects/2026/launch?source=newsletter</div></article><article><h3>{{ __('Copy the short one') }}</h3><p>{{ __('It is ready in a second, with a QR code one click away.') }}</p><div class="h-tut-art">hel.ink/l/launch ✓</div></article><article><h3>{{ __('Watch it travel') }}</h3><p>{{ __('With a free HOP ID the link stays yours: clicks, countries, devices and referrers.') }}</p><div class="h-tut-art">1,284 {{ __('clicks') }} · 72% {{ __('Mobile') }}</div></article></div>
</section>

{{-- 11. self-host --}}
<section class="h-sec h-stage" id="open" aria-labelledby="open-title">
    <div class="h-stage-copy"><p class="h-label"><span class="n">09</span> · <span>MIT</span></p><h2 id="open-title" data-split>{{ __('Free & open source') }} <em>{{ __('and yours to host.') }}</em></h2><p class="h-sub" data-rv>{{ __('Built with Laravel, Tailwind and Alpine.js. Use hel.ink or host it yourself.') }}</p><div class="h-badges" data-rv><a class="h-badge" href="https://github.com/navi-crwn/LinkHop/blob/main/LICENSE"><span>{{ __('License') }}</span><b>MIT</b></a><span class="h-badge"><span>PHP</span><b>8.3+</b></span><span class="h-badge"><span>Laravel</span><b>12</b></span></div><div class="h-cta"><a class="h-pill dark" href="https://github.com/navi-crwn/LinkHop">GitHub ↗</a></div></div>
    <div class="h-stage-visual h-shell" data-rv><div class="h-shell-bar"><i></i><i></i><i></i><span>bash</span></div><div class="h-term"><code id="install-sh">git clone https://github.com/navi-crwn/LinkHop.git && cd LinkHop && composer install && npm install && npm run build</code><button type="button" data-copy-text data-copy-from="#install-sh">{{ __('Copy') }}</button></div><pre>cp .env.example .env && php artisan key:generate
php artisan migrate && php artisan storage:link
<span class="ok">✓ php artisan serve</span></pre></div>
</section>

{{-- 12. FAQ --}}
<section class="h-sec" id="faq" aria-labelledby="home-faq">
    <p class="h-label"><span class="n">10</span> · <span>FAQ</span></p><h2 id="home-faq" data-split>{{ __('Questions,') }} <em>{{ __('answered.') }}</em></h2><p class="h-sub" data-rv>{{ __('Small URL.') }} {{ __('Serious possibilities.') }}</p>
    <div class="h-faq-grid">@foreach (['Is it really free?' => 'Yes. There is no paid plan and no locked feature, and the code is MIT licensed.', 'Do I need an account?' => 'Not to shorten a link. A free HOP ID keeps your links, shows their stats, and also signs you in to BioHop, PixelHop and NoteHop.', 'What does a click record?' => 'The time, country and city, device, browser and referrer, plus a keyed hash of the IP address for counting unique visitors. Never the IP address itself.', 'How long do guest links last?' => 'Guest links expire after :idle days without a click or :max days in total. Links made with a HOP ID stay until you delete them.', 'Can I change where a link goes?' => 'Yes. Edit the destination any time; the short link and its stats stay the same.', 'Can I report a link?' => 'Yes. Every hel.ink link has a report option, and the check page shows warnings before you open anything.'] as $question => $answer)
        <details><summary>{{ __($question) }}</summary><p>{{ __($answer, ['idle' => $idleDays, 'max' => $maxDays]) }}</p></details>
    @endforeach</div>
</section>

{{-- 13. family --}}
<section class="h-sec" id="family" aria-labelledby="family-title"><p class="h-label"><span class="n">11</span> · <span>HOP</span></p><h2 id="family-title" data-split><em>{{ __('HOP family') }}</em></h2><p class="h-sub" data-rv>{{ __('One free HOP ID signs you in to every app.') }}</p><div class="h-family" data-rv-group>@foreach (collect(\Hop\Ui\Assets::family('linkhop'))->reject(fn ($app) => $app['current']) as $app)<a href="{{ $app['url'] }}"><img src="{{ $app['icons']['mark']['url'] }}" alt="" width="32" height="32" loading="lazy">{{ $app['name'] }}</a>@endforeach</div></section>

{{-- 14. the last word --}}
<section class="h-sec h-final" aria-labelledby="final-title"><h2 id="final-title" data-split>{{ __('Less link.') }} <em>{{ __('More possibility.') }}</em></h2><p class="h-sub">{{ __('Shorten. Share. Understand.') }}</p><div class="h-cta"><a class="h-pill dark" href="#target_url">{{ __('Shorten') }} ↑</a><a class="h-pill" href="{{ route('register') }}">{{ __('Create free account') }}</a></div></section>
