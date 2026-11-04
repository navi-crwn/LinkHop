<x-app-layout>
    <x-slot name="pageTitle">{{ __('API') }}</x-slot>
    <x-page-header title="API documentation" subtitle="Use hel.ink from ShareX, the command line or your own apps.">
        <x-slot name="actions">
            <a href="{{ hub_url('settings') }}#api" class="btn-soft btn-sm">Manage API tokens (HOP ID)</a>
        </x-slot>
    </x-page-header>
    @php
        $code = 'glass-inset overflow-x-auto rounded-xl p-3 font-mono text-xs leading-relaxed text-ink';
    @endphp

    <div class="grid items-start gap-3 lg:grid-cols-3">
        <div class="min-w-0 space-y-3 lg:col-span-2">
            <section class="glass-card min-w-0 space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="chip-green font-mono">POST</span>
                    <h2 class="font-mono text-sm font-semibold text-ink">/api/shorten</h2>
                    <span class="text-xs text-muted">Create a short link</span>
                </div>
                <div>
                    <p class="eyebrow mb-1.5">Headers</p>
                    <pre tabindex="0" role="region" aria-label="{{ __('Code example') }}" class="{{ $code }}"><code>Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json</code></pre>
                </div>
                <div>
                    <p class="eyebrow mb-1.5">Request body</p>
                    <pre tabindex="0" role="region" aria-label="{{ __('Code example') }}" class="{{ $code }}"><code>{
  "url": "https://example.com/very/long/url",
  "alias": "my-custom-slug",
  "folder": "Marketing",
  "expires_at": "2026-12-31T23:59:00Z",
  "starts_at": "2026-12-01T00:00:00Z",
  "max_clicks": 500,
  "fallback_url": "https://example.com/offer-ended",
  "redirect_type": "302",
  "password": "secret123"
}</code></pre>
                    <div class="mt-2 overflow-x-auto">
                        <table class="table-glass">
                            <thead><tr><th>Field</th><th>Required</th><th>Notes</th></tr></thead>
                            <tbody class="text-xs">
                                <tr><td class="font-mono text-ink">url</td><td><span class="chip-amber">Yes</span></td><td class="text-muted">Destination URL</td></tr>
                                <tr><td class="font-mono text-ink">alias</td><td class="text-muted">No</td><td class="text-muted">At least {{ config('shortener.min_custom_slug_length', 3) }} characters (letters, numbers, - and _). Leave empty for a random 6-character slug.</td></tr>
                                <tr><td class="font-mono text-ink">folder</td><td class="text-muted">No</td><td class="text-muted">Folder name</td></tr>
                                <tr><td class="font-mono text-ink">expires_at</td><td class="text-muted">No</td><td class="text-muted">Date the link stops working</td></tr>
                                <tr><td class="font-mono text-ink">password</td><td class="text-muted">No</td><td class="text-muted">Visitors must enter it first</td></tr>
                                <tr><td class="font-mono text-ink">starts_at</td><td class="text-muted">No</td><td class="text-muted">The link says "not live yet" until this moment</td></tr>
                                <tr><td class="font-mono text-ink">max_clicks</td><td class="text-muted">No</td><td class="text-muted">Stops after this many counted clicks (crawlers and preview bots are not counted)</td></tr>
                                <tr><td class="font-mono text-ink">fallback_url</td><td class="text-muted">No</td><td class="text-muted">Where visitors go after the expiry date or when the clicks run out; cannot be another short link</td></tr>
                                <tr><td class="font-mono text-ink">redirect_type</td><td class="text-muted">No</td><td class="text-muted">301, 302 (default), 307 or 308</td></tr>
                                <tr><td class="font-mono text-ink">routing_rules</td><td class="text-muted">No</td><td class="text-muted">Up to 20 rules; the first that fits the visitor decides, everyone else (and every crawler) goes to <code>url</code>. A rule needs at least one of <code>country</code> (list of ISO codes), <code>language</code> (list, first Accept-Language entry), <code>device</code> (mobile, tablet, desktop), <code>os</code> (ios, android, windows, macos, linux, chromeos) or <code>browser</code> (chrome, safari, firefox, edge, opera, samsung), and a <code>url</code> that passes the same safety checks as <code>url</code></td></tr>
                                <tr><td class="font-mono text-ink">ab_variants</td><td class="text-muted">No</td><td class="text-muted">2 to 5 objects with <code>url</code> and a whole-number <code>weight</code> (1 to 1000, default 1). The same visitor always gets the same one; a matching routing rule comes first</td></tr>
                                <tr><td class="font-mono text-ink">app_links</td><td class="text-muted">No</td><td class="text-muted">Open the app on iOS and Android. An object with <code>mode</code> (<code>auto</code>: the destination is recognised, for example YouTube, Spotify, WhatsApp, Telegram, Instagram, X, TikTok, Zoom, App Store or Google Play; or <code>custom</code>: give <code>ios</code> and/or <code>android</code>, a URL scheme, an https app link or an <code>intent://</code> address) and optional <code>ios_store</code> and <code>android_store</code> pages. Visitors without the app go to the store page or the destination; desktops and crawlers always get a plain redirect</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="eyebrow mb-1.5 mt-3">Routing rules and A/B split</p>
                    <pre tabindex="0" role="region" aria-label="{{ __('Code example') }}" class="{{ $code }}"><code>{
  "url": "https://example.com/en",
  "routing_rules": [
    { "country": ["DE", "AT", "CH"], "url": "https://example.com/de" },
    { "device": "mobile", "os": "ios", "url": "https://apps.apple.com/app/id123" }
  ],
  "ab_variants": [
    { "url": "https://example.com/landing-a", "weight": 70 },
    { "url": "https://example.com/landing-b", "weight": 30 }
  ],
  "app_links": { "mode": "auto", "ios_store": "https://apps.apple.com/app/id123" }
}</code></pre>
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="min-w-0">
                        <p class="eyebrow mb-1.5">Success <span class="chip-green ml-1">201</span></p>
                        <pre tabindex="0" role="region" aria-label="{{ __('Code example') }}" class="{{ $code }}"><code>{
  "success": true,
  "data": {
    "url": "https://hel.ink/abc123",
    "slug": "abc123",
    "target_url": "https://example.com/...",
    "created_at": "2025-12-01T13:30:00Z",
    "expires_at": null
  },
  "message": "Link created successfully"
}</code></pre>
                    </div>
                    <div class="min-w-0">
                        <p class="eyebrow mb-1.5">Error <span class="chip-red ml-1">401 · 422 · 429</span></p>
                        <pre tabindex="0" role="region" aria-label="{{ __('Code example') }}" class="{{ $code }}"><code>{
  "success": false,
  "error": "Unauthorized",
  "message": "Invalid API token"
}</code></pre>
                    </div>
                </div>
            </section>

            <section class="glass-card space-y-2">
                <h2 class="section-title">cURL example</h2>
                <pre tabindex="0" role="region" aria-label="{{ __('Code example') }}" class="{{ $code }}"><code>curl -X POST {{ url('/api/shorten') }} \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://example.com/page",
    "alias": "mylink"
  }'</code></pre>
            </section>

            {{-- Batch D: idempotency keys and webhooks --}}
            <section id="idempotency" class="glass-card min-w-0 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="chip-blue font-mono">Header</span>
                    <h2 class="font-mono text-sm font-semibold text-ink">Idempotency-Key</h2>
                    <span class="text-xs text-muted">{{ __('Retry a create request without making the link twice') }}</span>
                </div>
                <p class="text-sm text-muted">{{ __('Send a unique key (a UUID is fine) with POST /api/shorten. If the connection drops and you send the same request again with the same key, you get the first answer back and no second link is made.') }}</p>
                <ul class="list-disc space-y-1 pl-5 text-xs text-muted">
                    <li>{{ __('Same key, same account and same body: the stored answer is returned with the header Idempotent-Replayed: true.') }}</li>
                    <li>{{ __('Same key with a different body or address: 422 "Idempotency Key Reuse". Use a new key for a new request.') }}</li>
                    <li>{{ __('Same key while the first request is still running: 409, try again in a moment.') }}</li>
                    <li>{{ __('Only successful answers (2xx) are kept, so a request that failed can be sent again with the same key. Keys are kept for 24 hours. A key is 1 to 255 printable characters.') }}</li>
                </ul>
                <pre tabindex="0" role="region" aria-label="{{ __('Code example') }}" class="{{ $code }}"><code>curl -X POST {{ url('/api/shorten') }} \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Idempotency-Key: 6f1c0b7e-3f58-4c0e-9d0e-2a2f4c9b1a10" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com/page"}'</code></pre>
            </section>

            <section id="webhooks" class="glass-card min-w-0 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="chip-blue font-mono">Webhooks</span>
                    <h2 class="section-title">{{ __('Signed messages when your links change') }}</h2>
                </div>
                <p class="text-sm text-muted">{{ __('Add an https:// address in Settings, Webhooks. LinkHop sends it a JSON message for the events you choose. The secret is shown once when you add the webhook.') }} <a href="{{ route('settings') }}#webhooks" class="text-ink underline">{{ __('Open Webhooks') }}</a></p>
                <div class="overflow-x-auto">
                    <table class="table-glass">
                        <thead><tr><th>{{ __('Event') }}</th><th>{{ __('When') }}</th></tr></thead>
                        <tbody class="text-xs">
                            <tr><td class="font-mono text-ink">link.created</td><td class="text-muted">{{ __('A link was made (dashboard, API or import).') }}</td></tr>
                            <tr><td class="font-mono text-ink">link.updated</td><td class="text-muted">{{ __('The address, slug, status, dates, limit, redirect, folder, password or social card changed. "changed" lists the fields.') }}</td></tr>
                            <tr><td class="font-mono text-ink">link.deleted</td><td class="text-muted">{{ __('A link was deleted.') }}</td></tr>
                            <tr><td class="font-mono text-ink">link.limit_reached</td><td class="text-muted">{{ __('The last allowed click was used (max_clicks).') }}</td></tr>
                            <tr><td class="font-mono text-ink">link.expired</td><td class="text-muted">{{ __('The expiry time passed. Sent within a few minutes.') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div>
                    <p class="eyebrow mb-1.5">{{ __('What you receive') }}</p>
                    <pre tabindex="0" role="region" aria-label="{{ __('Code example') }}" class="{{ $code }}"><code>POST /your/endpoint
Content-Type: application/json
User-Agent: LinkHop-Webhook/1
X-LinkHop-Event: link.created
X-LinkHop-Delivery: 5c1d7c52-8d2e-4a43-9a37-6b2f2d2a1b10
X-LinkHop-Timestamp: 1793894400
X-LinkHop-Signature: sha256=3f7a...c91

{
  "id": "5c1d7c52-8d2e-4a43-9a37-6b2f2d2a1b10",
  "event": "link.created",
  "created_at": "2026-11-05T10:00:00+00:00",
  "data": {
    "link": {
      "id": 42, "slug": "abc123", "short_url": "https://hel.ink/l/abc123",
      "target_url": "https://example.com/page", "status": "active",
      "clicks": 0, "max_clicks": null, "expires_at": null, "password_protected": false
    }
  }
}</code></pre>
                </div>
                <div>
                    <p class="eyebrow mb-1.5">{{ __('Check the signature') }}</p>
                    <p class="mb-1.5 text-xs text-muted">{{ __('The signature is HMAC-SHA256 of the timestamp, a dot and the raw body, with your secret as the key. Compare it in constant time and refuse timestamps older than five minutes.') }}</p>
                    <pre tabindex="0" role="region" aria-label="{{ __('Code example') }}" class="{{ $code }}"><code>$body = file_get_contents('php://input');
$ts   = $_SERVER['HTTP_X_LINKHOP_TIMESTAMP'];
$sig  = 'sha256=' . hash_hmac('sha256', $ts . '.' . $body, $secret);
$ok   = abs(time() - (int) $ts) < 300
     && hash_equals($sig, $_SERVER['HTTP_X_LINKHOP_SIGNATURE']);</code></pre>
                    <pre tabindex="0" role="region" aria-label="{{ __('Code example') }}" class="{{ $code }} mt-2"><code>// Node.js (Express, with the raw body)
const sig = 'sha256=' + crypto.createHmac('sha256', secret)
  .update(req.get('X-LinkHop-Timestamp') + '.' + rawBody).digest('hex');
const ok = Math.abs(Date.now() / 1000 - Number(req.get('X-LinkHop-Timestamp'))) < 300
  && crypto.timingSafeEqual(Buffer.from(sig), Buffer.from(req.get('X-LinkHop-Signature')));</code></pre>
                </div>
                <ul class="list-disc space-y-1 pl-5 text-xs text-muted">
                    <li>{{ __('Answer with any 2xx within 5 seconds. Anything else counts as a failure.') }}</li>
                    <li>{{ __('A failed delivery is tried again after 1 minute, 5 minutes, 30 minutes and 2 hours (five tries). The id in X-LinkHop-Delivery stays the same, so you can ignore repeats.') }}</li>
                    <li>{{ __('Redirects are not followed. Addresses on private or internal networks are refused.') }}</li>
                    <li>{{ __('After 10 deliveries in a row fail, the webhook is switched off. You can switch it on again in Settings.') }}</li>
                    <li>{{ __('Up to 5 webhooks per account. The last 50 deliveries of each are kept in the log.') }}</li>
                </ul>
            </section>
        </div>

        <aside class="space-y-3 lg:sticky lg:top-24">
            <section class="glass-card space-y-2">
                <h2 class="section-title">Quick start</h2>
                <ol class="list-decimal space-y-1 pl-4 text-sm text-muted marker:text-ink">
                    <li>Open <a href="{{ hub_url('settings') }}#api" class="text-ink underline">HOP ID → Settings → API</a> (tokens are made at the hub and work in every HOP app).</li>
                    <li>Create a token with a name you'll recognise and the <code class="rounded-sm bg-slate-500/10 px-1 font-mono text-xs text-ink">link:*</code> scopes you need.</li>
                    <li>Copy it now. It is shown only once.</li>
                    <li>Send it as <code class="rounded-sm bg-slate-500/10 px-1 font-mono text-xs text-ink">Authorization: Bearer …</code></li>
                </ol>
            </section>

            <section class="glass-card space-y-3">
                <div>
                    <h2 class="section-title">ShareX</h2>
                    <p class="mt-0.5 text-xs text-muted">Upload a screenshot on Windows and get a short link back.</p>
                </div>
                <ol class="list-decimal space-y-1 pl-4 text-xs text-muted marker:text-ink">
                    <li>Create an API token.</li>
                    <li>Download the config below and open it with ShareX.</li>
                    <li>Replace <code class="font-mono text-ink">YOUR_API_TOKEN_HERE</code> with your token.</li>
                </ol>
                <form method="POST" action="{{ route('api.sharex-config') }}">
                    @csrf
                    <button type="submit" class="btn-primary btn-sm w-full justify-center">Download ShareX config (.sxcu)</button>
                </form>
            </section>

            <section class="glass-card space-y-2">
                <h2 class="section-title">Rate limit</h2>
                <dl class="space-y-1 text-xs">
                    <div class="flex justify-between gap-2"><dt class="text-muted">Per token</dt><dd class="text-ink">100 requests / hour</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">When exceeded</dt><dd class="text-ink">429 Too Many Requests</dd></div>
                </dl>
                <p class="text-xs text-muted">Use a separate token for each app.</p>
            </section>

            <section class="glass-card space-y-2">
                <h2 class="section-title">Works with</h2>
                <div class="flex flex-wrap gap-1.5">
                    <span class="chip-gray">Flameshot</span>
                    <span class="chip-gray">Greenshot</span>
                    <span class="chip-gray">Alfred</span>
                    <span class="chip-gray">Python / Node scripts</span>
                </div>
            </section>

            <section class="glass-card flex gap-2">
                <a href="mailto:support@hel.ink" class="btn-soft btn-sm flex-1 justify-center">Email support</a>
                <a href="https://github.com/navi-crwn/hel.ink/issues" target="_blank" rel="noopener" class="btn-ghost btn-sm flex-1 justify-center">Report an issue</a>
            </section>
        </aside>
    </div>
</x-app-layout>
