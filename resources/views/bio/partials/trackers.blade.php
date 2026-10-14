{{-- Owner-configured trackers load only after the visitor agrees (P7). The choice is remembered per page in this browser. --}}
@php
    $ga = $bioPage->google_analytics_id && preg_match('/^G-[A-Z0-9]+$/', $bioPage->google_analytics_id) ? $bioPage->google_analytics_id : null;
    $fb = $bioPage->facebook_pixel_id && preg_match('/^\d{10,20}$/', $bioPage->facebook_pixel_id) ? $bioPage->facebook_pixel_id : null;
    $tt = $bioPage->tiktok_pixel_id && preg_match('/^[A-Z0-9]{20,30}$/', $bioPage->tiktok_pixel_id) ? $bioPage->tiktok_pixel_id : null;
    $trackerNames = array_keys(array_filter(['Google Analytics' => $ga, 'Facebook' => $fb, 'TikTok' => $tt]));
@endphp
@if($trackerNames)
    <div id="tracker-consent" hidden role="region" aria-label="Tracking consent"
         class="fixed inset-x-0 bottom-0 p-4 sm:p-6" style="z-index: 999998;">
        <div class="mx-auto max-w-md rounded-2xl bg-slate-900/95 p-4 text-sm text-white shadow-xl ring-1 ring-white/10 backdrop-blur">
            <p>The owner of this page uses {{ implode(', ', $trackerNames) }} to measure visits. Allow this tracking?</p>
            <div class="mt-3 flex gap-2">
                <button type="button" id="tracker-consent-allow" class="rounded-xl bg-white px-4 py-2 font-semibold text-slate-900 hover:bg-white/90">Allow</button>
                <button type="button" id="tracker-consent-decline" class="rounded-xl border border-white/30 px-4 py-2 font-semibold text-white hover:bg-white/10">Decline</button>
            </div>
        </div>
    </div>
    <script nonce="{{ Vite::cspNonce() }}">
        (function () {
            const key = 'helink-trackers:' + @js($bioPage->slug);
            const ga = @js($ga), fb = @js($fb), tt = @js($tt);
            function load() {
                if (ga) {
                    const s = document.createElement('script');
                    s.async = true;
                    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga);
                    document.head.appendChild(s);
                    window.dataLayer = window.dataLayer || [];
                    window.gtag = function () { window.dataLayer.push(arguments); };
                    window.gtag('js', new Date());
                    window.gtag('config', ga);
                }
                if (fb) {
                    !function(f,b,e,v,n,t,s)
                    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                    n.queue=[];t=b.createElement(e);t.async=!0;
                    t.src=v;s=b.getElementsByTagName(e)[0];
                    s.parentNode.insertBefore(t,s)}(window, document,'script',
                    'https://connect.facebook.net/en_US/fbevents.js');
                    window.fbq('init', fb);
                    window.fbq('track', 'PageView');
                }
                if (tt) {
                    !function (w, d, t) {
                        w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
                        ttq.load(tt);
                        ttq.page();
                    }(window, document, 'ttq');
                }
            }
            function remember(value) {
                try { localStorage.setItem(key, value); } catch (e) { /* storage blocked: ask again next visit */ }
            }
            let choice = null;
            try { choice = localStorage.getItem(key); } catch (e) { /* storage blocked */ }
            if (choice === 'yes') { load(); return; }
            if (choice === 'no') return;
            const banner = document.getElementById('tracker-consent');
            banner.hidden = false;
            document.getElementById('tracker-consent-allow').addEventListener('click', function () {
                remember('yes');
                banner.hidden = true;
                load();
            });
            document.getElementById('tracker-consent-decline').addEventListener('click', function () {
                remember('no');
                banner.hidden = true;
            });
        })();
    </script>
@endif
