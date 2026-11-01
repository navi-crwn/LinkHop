@php
    $revealed = session('webhook_secret');
    $errorLabels = [
        'blocked' => __('Address not allowed'),
        'connect' => __('Could not connect'),
        'http' => __('The receiver did not accept it'),
        'off' => __('Webhook is off'),
        'error' => __('Something went wrong'),
    ];
@endphp
<div x-show="tab === 'webhooks'" x-cloak class="space-y-4 p-5">
    <div>
        <h3 class="text-base font-semibold text-ink">{{ __('Webhooks') }}</h3>
        <p class="text-sm text-muted">{{ __('Get a signed JSON message at your own address when a link is created, changed, deleted, runs out of clicks or expires. Each message is signed with a secret that only you and LinkHop know.') }} <a href="{{ route('api-docs') }}#webhooks" class="underline">{{ __('How to check the signature') }}</a></p>
    </div>

    @if ($revealed)
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4" role="status">
            <p class="text-sm font-medium text-ink">{{ __('Your signing secret') }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <input id="webhook-secret" type="text" readonly value="{{ $revealed['secret'] }}" class="input min-w-0 flex-1 font-mono text-xs" aria-label="{{ __('Your signing secret') }}" @focus="$el.select()">
                <button type="button" class="btn-primary btn-sm" data-copied="{{ __('Copied') }}" data-failed="{{ __('Copy failed') }}" @click="hopCopy(@js($revealed['secret']), $el)">{{ __('Copy') }}</button>
            </div>
            <p class="mt-2 text-xs text-muted">{{ __('It is shown only once. Keep it in your server settings, never in a web page or a repository.') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('webhooks.store') }}" class="glass-inset space-y-3 rounded-xl p-4">
        @csrf
        <div>
            <label for="webhook-url" class="label">{{ __('Address that receives the messages') }}</label>
            <input id="webhook-url" type="url" name="url" value="{{ old('url') }}" required maxlength="2000" placeholder="https://example.com/hooks/linkhop" class="input">
            <x-input-error :messages="$errors->get('url')" class="mt-1" />
            <p class="mt-1 text-xs text-muted">{{ __('Only https:// addresses on the public internet. You can have up to :max.', ['max' => \App\Support\Webhooks::MAX_ENDPOINTS]) }}</p>
        </div>
        <fieldset>
            <legend class="label">{{ __('Events') }}</legend>
            <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                @foreach (\App\Support\Webhooks::EVENTS as $event)
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="events[]" value="{{ $event }}" checked class="rounded-sm border-slate-400/50 text-indigo-600 focus:ring-indigo-500">
                        <span class="font-mono text-xs">{{ $event }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <button type="submit" class="btn-primary btn-sm" @disabled($webhooks->count() >= \App\Support\Webhooks::MAX_ENDPOINTS)>{{ __('Add webhook') }}</button>
    </form>

    @forelse ($webhooks as $hook)
        <div class="glass-inset space-y-3 rounded-xl p-4">
            <div class="flex flex-wrap items-center gap-2">
                <p class="min-w-0 flex-1 truncate font-mono text-sm text-ink" title="{{ $hook->displayHost() }}">{{ $hook->displayHost() }}</p>
                <span class="{{ $hook->is_active ? 'chip-green' : 'chip-gray' }}">{{ $hook->is_active ? __('On') : __('Off') }}</span>
            </div>
            <p class="text-xs text-muted">
                {{ $hook->events ? implode(', ', $hook->events) : __('All events') }}
                @if ($hook->failure_streak > 0)
                    · {{ __(':count deliveries in a row failed.', ['count' => $hook->failure_streak]) }}
                @endif
                @if (! $hook->is_active && $hook->failure_streak >= \App\Support\Webhooks::DISABLE_AFTER)
                    · {{ __('Switched off after too many failures.') }}
                @endif
            </p>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('webhooks.test', $hook) }}">@csrf<button type="submit" class="btn-soft btn-sm">{{ __('Send test') }}</button></form>
                <form method="POST" action="{{ route('webhooks.update', $hook) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $hook->is_active ? 0 : 1 }}"><button type="submit" class="btn-soft btn-sm">{{ $hook->is_active ? __('Switch off') : __('Switch on') }}</button></form>
                <form method="POST" action="{{ route('webhooks.rotate', $hook) }}" x-data @submit="if (!confirm(@js(__('Make a new secret? The old one stops working at once.')))) $event.preventDefault()">@csrf<button type="submit" class="btn-soft btn-sm">{{ __('New secret') }}</button></form>
                <form method="POST" action="{{ route('webhooks.destroy', $hook) }}" x-data @submit="if (!confirm(@js(__('Delete this webhook and its log?')))) $event.preventDefault()">@csrf @method('DELETE')<button type="submit" class="btn-ghost btn-sm text-rose-600 dark:text-rose-300">{{ __('Delete') }}</button></form>
            </div>
            <details class="text-sm">
                <summary class="cursor-pointer text-muted">{{ __('Log of the last deliveries') }} ({{ $hook->deliveries->count() }})</summary>
                @if ($hook->deliveries->isEmpty())
                    <p class="mt-2 text-xs text-muted">{{ __('Nothing sent yet.') }}</p>
                @else
                    <div class="mt-2 overflow-x-auto">
                        <table class="table-glass">
                            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Event') }}</th><th>{{ __('Result') }}</th><th>{{ __('Tries') }}</th></tr></thead>
                            <tbody class="text-xs">
                                @foreach ($hook->deliveries as $delivery)
                                    <tr>
                                        <td class="whitespace-nowrap text-muted">{{ $delivery->created_at->format('M d, H:i:s') }}</td>
                                        <td class="font-mono text-ink">{{ $delivery->event }}</td>
                                        <td>
                                            <span class="{{ ['sent' => 'chip-green', 'failed' => 'chip-red'][$delivery->status] ?? 'chip-amber' }}">{{ ['sent' => __('Delivered'), 'failed' => __('Failed')][$delivery->status] ?? __('Waiting') }}</span>
                                            @if ($delivery->http_code) <span class="text-muted">{{ $delivery->http_code }}</span> @endif
                                            @if ($delivery->error) <span class="text-muted">{{ $errorLabels[$delivery->error] ?? $delivery->error }}</span> @endif
                                            @if ($delivery->status === 'pending' && $delivery->next_attempt_at) <span class="text-muted">· {{ __('next try :time', ['time' => $delivery->next_attempt_at->diffForHumans()]) }}</span> @endif
                                        </td>
                                        <td class="text-muted">{{ $delivery->attempts }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </details>
        </div>
    @empty
        <p class="text-sm text-muted">{{ __('No webhooks yet.') }}</p>
    @endforelse
</div>
