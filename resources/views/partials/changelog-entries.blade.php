<ol class="space-y-4" aria-label="{{ __('Changelog') }}">
    @foreach($entries as $entry)
        <li class="rounded-2xl border border-slate-200 p-5 dark:border-white/10">
            <div class="flex flex-wrap items-baseline gap-3 text-sm text-slate-500 dark:text-slate-400">
                <strong>{{ $entry['app'] }}</strong>
                @if(isset($entry['items']))
                    <span class="font-mono">{{ $entry['id'] }}</span>
                @endif
                <time datetime="{{ $entry['date'] }}">{{ substr($entry['date'], 0, 10) }}</time>
                @unless(isset($entry['items']))
                    <span>{{ __(['commit' => 'Commit', 'pr' => 'Merged pull request', 'tag' => 'Release'][$entry['kind']]) }}</span>
                @endunless
            </div>
            @if(isset($entry['items']))
                <h2 class="mt-2 break-words text-lg font-semibold">{{ $entry['title'] }}</h2>
                @if(($entry['summary'] ?? '') !== '')<p class="mt-1 text-slate-600 dark:text-slate-300">{{ $entry['summary'] }}</p>@endif
                @foreach(collect($entry['items'])->groupBy('type') as $type => $items)
                    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __(ucfirst($type)) }}</h3>
                    <ul class="mt-2 list-disc space-y-2 pl-5 text-slate-700 dark:text-slate-200">
                        @foreach($items as $item)
                            <li class="break-words"><strong>{{ $item['where'] }}.</strong> {{ $item['text'] }}</li>
                        @endforeach
                    </ul>
                @endforeach
                <p class="mt-4 text-sm"><a href="{{ $entry['url'] }}" rel="noopener">{{ __('Technical details') }} &rarr;</a></p>
            @else
                <h2 class="mt-2 break-words text-lg font-semibold"><a href="{{ $entry['url'] }}" rel="noopener">{{ $entry['title'] }}</a></h2>
                <span class="text-sm text-slate-500">{{ $entry['id'] }}</span>
            @endif
        </li>
    @endforeach
</ol>
<nav class="my-8 flex justify-between gap-4" aria-label="{{ __('Pagination') }}">
    @if($page > 1)<a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">&larr; {{ __('Newer') }}</a>@else<span></span>@endif
    <span>{{ $page }} / {{ $pages }}</span>
    @if($page < $pages)<a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">{{ __('Older') }} &rarr;</a>@else<span></span>@endif
</nav>
<details class="mt-6 text-sm text-slate-500">
    <summary>{{ __('Latest release of each app') }} ({{ $total }})</summary>
    <ul>@foreach($sources as $repo => $revision)<li class="break-all">{{ $repo }} · @if(preg_match('/^[0-9a-f]{40}$/', $revision))<a href="https://github.com/navi-crwn/{{ $repo }}/commit/{{ $revision }}">{{ substr($revision, 0, 12) }}</a>@else v{{ $revision }}@endif</li>@endforeach</ul>
</details>
