<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - API</x-slot>
    <x-page-header title="API documentation" subtitle="Use hel.ink from ShareX, the command line or your own apps.">
        <x-slot name="actions">
            <a href="{{ route('settings') }}#api" class="btn-soft btn-sm">Manage API tokens</a>
        </x-slot>
    </x-page-header>
    @php
        $code = 'glass-inset overflow-x-auto rounded-xl p-3 font-mono text-xs leading-relaxed text-ink';
    @endphp

    <div class="grid items-start gap-3 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            <section class="glass-card space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="chip-green font-mono">POST</span>
                    <h2 class="font-mono text-sm font-semibold text-ink">/api/shorten</h2>
                    <span class="text-xs text-muted">Create a short link</span>
                </div>
                <div>
                    <p class="eyebrow mb-1.5">Headers</p>
                    <pre class="{{ $code }}"><code>Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json</code></pre>
                </div>
                <div>
                    <p class="eyebrow mb-1.5">Request body</p>
                    <pre class="{{ $code }}"><code>{
  "url": "https://example.com/very/long/url",
  "alias": "my-custom-slug",
  "folder": "Marketing",
  "expires_at": "2025-12-31",
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
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="eyebrow mb-1.5">Success <span class="chip-green ml-1">201</span></p>
                        <pre class="{{ $code }}"><code>{
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
                    <div>
                        <p class="eyebrow mb-1.5">Error <span class="chip-red ml-1">401 · 422 · 429</span></p>
                        <pre class="{{ $code }}"><code>{
  "success": false,
  "error": "Unauthorized",
  "message": "Invalid API token"
}</code></pre>
                    </div>
                </div>
            </section>

            <section class="glass-card space-y-2">
                <h2 class="section-title">cURL example</h2>
                <pre class="{{ $code }}"><code>curl -X POST {{ url('/api/shorten') }} \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://example.com/page",
    "alias": "mylink"
  }'</code></pre>
            </section>
        </div>

        <aside class="space-y-3 lg:sticky lg:top-24">
            <section class="glass-card space-y-2">
                <h2 class="section-title">Quick start</h2>
                <ol class="list-decimal space-y-1 pl-4 text-sm text-muted marker:text-ink">
                    <li>Open <a href="{{ route('settings') }}#api" class="text-ink underline">Settings → API</a>.</li>
                    <li>Create a token with a name you'll recognise.</li>
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
