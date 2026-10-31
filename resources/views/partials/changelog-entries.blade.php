<ol class="space-y-4" aria-label="{{ __('Changelog') }}">
    @foreach($entries as $entry)
        <li class="rounded-2xl border border-slate-200 p-5 dark:border-white/10">
            <div class="flex flex-wrap gap-3 text-sm text-slate-500 dark:text-slate-400">
                <strong>{{ $entry['app'] }}</strong>
                <time datetime="{{ $entry['date'] }}">{{ substr($entry['date'], 0, 10) }}</time>
                <span>{{ __(['commit' => 'Commit', 'pr' => 'Merged pull request', 'tag' => 'Release'][$entry['kind']]) }}</span>
            </div>
            <h2 class="mt-2 break-words text-lg font-semibold"><a href="{{ $entry['url'] }}" rel="noopener">{{ $entry['title'] }}</a></h2>
            <span class="text-sm text-slate-500">{{ $entry['id'] }}</span>
        </li>
    @endforeach
</ol>
<nav class="my-8 flex justify-between gap-4" aria-label="{{ __('Pagination') }}">
    @if($page > 1)<a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">&larr; {{ __('Newer') }}</a>@else<span></span>@endif
    <span>{{ $page }} / {{ $pages }}</span>
    @if($page < $pages)<a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">{{ __('Older') }} &rarr;</a>@else<span></span>@endif
</nav>
<details class="mt-6 text-sm text-slate-500">
    <summary>{{ __('Source revisions') }} ({{ $total }})</summary>
    <ul>@foreach($sources as $repo => $revision)<li class="break-all">{{ $repo }} · <a href="https://github.com/navi-crwn/{{ $repo }}/commit/{{ $revision }}">{{ substr($revision, 0, 12) }}</a></li>@endforeach</ul>
</details>
