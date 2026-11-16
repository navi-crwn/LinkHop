<x-public-layout :title="__('Check a link').' · LinkHop'" :description="__('Paste a short link to see where it goes before you open it. LinkHop shows the destination and warns about unsafe links, without clicking or tracking.')">
    @php
        $card = 'rounded-3xl border border-white/10 bg-white/4 backdrop-blur-xl';
        $host = public_host();
    @endphp
    <section class="mx-auto max-w-2xl px-4 pb-10 pt-14 sm:pt-20">
        <h1 class="text-center text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('Where does this link go?') }}</h1>
        <p class="mx-auto mt-3 max-w-md text-center text-white/60">{{ __('Paste a :host link to see its destination before you open it. Nothing is clicked or tracked.', ['host' => $host]) }}</p>

        <form method="GET" action="{{ route('link.check') }}" class="{{ $card }} mt-8 flex flex-col gap-2 p-2 sm:flex-row">
            <label for="url" class="sr-only">{{ __('Short link') }}</label>
            <input id="url" name="url" value="{{ $input }}" required autofocus placeholder="{{ $host }}/abc123"
                data-ctl-max="640" class="input input-lg min-w-0 flex-1 max-w-none!">
            <button class="btn-primary btn-lg">{{ __('Check') }}</button>
        </form>

        @if ($result)
            <div class="{{ $card }} mt-4 p-5 sm:p-6">
                @switch($result['type'])
                    @case('foreign')
                        <p class="font-medium">{{ __("That isn't a :host link.", ['host' => $host]) }}</p>
                        <p class="mt-1 text-sm text-white/60">{{ __('This tool only checks links on :host (you entered :entered).', ['host' => $host, 'entered' => $result['host']]) }}</p>
                        @break

                    @case('missing')
                        <p class="font-medium">{{ __('No link found.') }}</p>
                        <p class="mt-1 text-sm text-white/60">{{ __(":path doesn't exist, was removed, or has expired.", ['path' => $host.'/l/'.$result['slug']]) }}</p>
                        @break

                    @case('bio')
                        <p class="text-xs font-semibold uppercase tracking-wider text-white/70">{{ __('Link in Bio page') }}</p>
                        <p class="mt-2 text-lg font-semibold">{{ $result['page']->title }}</p>
                        <p class="text-sm text-white/60">{{ __(':path · :count links', ['path' => $host.'/b/'.$result['page']->slug, 'count' => $result['page']->links_count]) }}</p>
                        <a href="{{ public_url('b/'.$result['page']->slug) }}" rel="noopener" class="mt-4 inline-block rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900">{{ __('Open page') }}</a>
                        @break

                    @case('link')
                        @php $link = $result['link']; @endphp
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-mono text-sm text-white/70">{{ $host }}/l/{{ $link->slug }}</p>
                            @if ($result['state'] === 'active')
                                <span class="rounded-full bg-emerald-400/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">{{ __('Active') }}</span>
                            @else
                                <span class="rounded-full bg-white/10 px-2 py-0.5 text-[11px] font-semibold text-white/70">{{ __(['suspended' => 'Suspended', 'inactive' => 'Inactive', 'expired' => 'Expired'][$result['state']] ?? ucfirst($result['state'])) }}</span>
                            @endif
                            @if ($link->isAdult())
                                <span class="rounded-full bg-rose-500/20 px-2 py-0.5 text-[11px] font-semibold text-rose-300 ring-1 ring-rose-500/30">{{ __('18+ Adult') }}</span>
                            @elseif ($link->isSensitive())
                                <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-[11px] font-semibold text-amber-300 ring-1 ring-amber-500/30">{{ __('Content Warning') }}</span>
                            @endif
                        </div>

                        @if ($link->warning_reason)
                            <p class="mt-2 text-xs text-amber-200/90 bg-amber-500/10 p-2.5 rounded-xl border border-amber-500/20">
                                <strong class="uppercase text-[10px] tracking-wider text-amber-300/80">{{ __('Creator Note:') }}</strong> {{ $link->warning_reason }}
                            </p>
                        @endif

                        @if ($result['protected'])
                            <p class="mt-4 font-medium">{{ __('This link is password protected.') }}</p>
                            <p class="mt-1 text-sm text-white/60">{{ __('Its destination is only shown after entering the password.') }}</p>
                        @elseif (! empty($result['hidden']))
                            <p class="mt-4 font-medium">{{ __('This link no longer works.') }}</p>
                            <p class="mt-1 text-sm text-white/70">{{ __('Its destination is not shown. Status: :state.', ['state' => __(['suspended' => 'Suspended', 'inactive' => 'Inactive', 'expired' => 'Expired'][$result['state']] ?? $result['state'])]) }}</p>
                        @else
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-white/70">{{ __('Destination') }}</p>
                            <p class="mt-1 break-all text-[15px] text-white" x-data>
                                {{ $link->target_url }}
                            </p>
                            <p class="mt-1 text-sm text-white/70">{{ __('Domain:') }} <span class="font-medium text-white/80">{{ $result['host'] }}</span></p>

                            @if ($result['blocked'])
                                <div class="mt-4 rounded-2xl bg-rose-500/15 p-3 text-sm text-rose-100 ring-1 ring-rose-400/30">
                                    <p class="font-semibold">{{ __('Unsafe destination') }}</p>
                                    <p class="mt-0.5 text-rose-100/80">{{ __("Flagged as: :reasons. Don't open it.", ['reasons' => implode(', ', $result['blocked'])]) }}</p>
                                </div>
                            @elseif ($result['signals'])
                                <div class="mt-4 rounded-2xl bg-amber-400/10 p-3 text-sm text-amber-100 ring-1 ring-amber-300/25">
                                    <p class="font-semibold">{{ __('Be careful') }}</p>
                                    <p class="mt-0.5 text-amber-100/80">{{ __('Notes: :notes.', ['notes' => str_replace('_', ' ', implode(', ', $result['signals']))]) }}</p>
                                </div>
                            @else
                                <p class="mt-4 text-sm text-emerald-300">{{ __('No known issues found for this destination.') }}</p>
                            @endif

                            <div class="mt-5 flex flex-wrap gap-2" x-data="{ copied: false }">
                                <button type="button" class="btn-outline"
                                    @click="navigator.clipboard.writeText(@js($link->target_url)); copied = true; setTimeout(() => copied = false, 2000)">
                                    <span x-text="copied ? @js(__('Copied')) : @js(__('Copy destination'))">{{ __('Copy destination') }}</span>
                                </button>
                                @if ($result['state'] === 'active' && ! $result['blocked'])
                                    <a href="{{ $link->target_url }}" rel="noopener noreferrer nofollow" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900">{{ __('Open destination') }}</a>
                                @endif
                            </div>
                        @endif
                        <p class="mt-5 border-t border-white/10 pt-4 text-xs text-white/70">
                            {{ $link->user_id ? __('Created :time.', ['time' => $link->created_at?->diffForHumans()]) : __('Created :time by a guest.', ['time' => $link->created_at?->diffForHumans()]) }}
                            {{ __('Something wrong?') }} <a href="{{ route('report.create', ['slug' => $link->slug]) }}" class="underline hover:text-white">{{ __('Report this link') }}</a>.
                        </p>
                        @break
                @endswitch
            </div>
        @endif
    </section>
</x-public-layout>
