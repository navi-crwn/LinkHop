@php
    // No cookie, no query: the count is only asked for when this browser holds a claim token.
    $claimable = auth()->check() ? \App\Support\GuestClaim::pending(request()) : 0;
@endphp
@if ($claimable > 0)
    <div class="glass flex flex-wrap items-center gap-3 rounded-2xl px-4 py-3 text-sm" role="region" aria-label="{{ __('Links made before signing in') }}" data-claim-notice>
        <span class="chip-blue">{{ __('Notice') }}</span>
        <span class="min-w-0 flex-1 text-ink">{{ $claimable === 1 ? __('You made 1 link before signing in.') : __('You made :count links before signing in.', ['count' => $claimable]) }}
            <span class="text-muted">{{ __('Claim them to manage them from your account: they stop expiring.') }}</span></span>
        <form method="POST" action="{{ route('links.claim') }}">
            @csrf
            <button type="submit" class="btn-primary btn-sm">{{ __('Claim') }}</button>
        </form>
    </div>
@endif
