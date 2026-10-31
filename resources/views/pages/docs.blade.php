<x-info-page title="API" lead="Create, read and delete short links from scripts and apps.">
    <h2 id="token">{{ __('Tokens') }}</h2>
    <p>{!! __('Create a token at the hub (<strong>HOP ID → Settings → API</strong>). A token carries scopes: <code>link:create</code>, <code>link:read</code>, <code>link:delete</code>. Send it as <code>Authorization: Bearer YOUR_TOKEN</code>.') !!}</p>
    <h2 id="create">{{ __('Create a short link') }}</h2>
    <pre><code>curl -X POST {{ url('/api/shorten') }} \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "alias": "my-link"}'</code></pre>
    <p>{!! __('Fields: <code>url</code> (required), <code>alias</code>, <code>folder</code>, <code>expires_at</code> (ISO date-time), <code>password</code>. The answer is <code>201</code> with the link in <code>data</code>.') !!}</p>
    <h2 id="read">{{ __('Read and delete') }}</h2>
    <p>{!! __('<code>GET /api/links/{slug}</code> returns the link with its clicks (<code>link:read</code>). <code>DELETE /api/links/{slug}</code> removes it (<code>link:delete</code>).') !!}</p>
    <h2 id="errors">{{ __('Errors') }}</h2>
    <p>{!! __('One shape, <code>{"success": false, "error": "…", "message": "…"}</code>: <code>401</code> unknown or expired token, <code>403</code> missing scope or suspended account, <code>422</code> validation, <code>429</code> quota or rate limit.') !!}</p>
    <h2 id="sharex">{{ __('ShareX') }}</h2>
    <p>{!! __('A ready-made ShareX config is on the API page of your <a href=":url">dashboard</a>.', ['url' => route('dashboard')]) !!}</p>
</x-info-page>
