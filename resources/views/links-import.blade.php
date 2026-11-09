<x-app-layout>
    <x-slot name="pageTitle">{{ __('Import links') }}</x-slot>
    <x-page-header :title="__('Import links from CSV')" :subtitle="__('Upload a file, check what would be created, then confirm. The file is read on this server and goes nowhere else.')">
        <x-slot name="actions">
            <a href="{{ route('links.import.sample') }}" class="btn-soft btn-sm">{{ __('Download sample CSV') }}</a>
            <a href="{{ route('links.index') }}" class="btn-ghost btn-sm">{{ __('My links') }}</a>
        </x-slot>
    </x-page-header>

    <x-flash />

    @if ($result)
        <section class="glass-card space-y-3" role="status">
            <h2 class="section-title">{{ trans_choice(':count link was created.|:count links were created.', $result['created'], ['count' => $result['created']]) }}</h2>
            @if ($result['skipped'])
                <p class="text-sm text-muted">{{ trans_choice(':count row was skipped:|:count rows were skipped:', count($result['skipped']), ['count' => count($result['skipped'])]) }}</p>
                <div class="overflow-x-auto">
                    <table class="table-glass">
                        <thead><tr><th>{{ __('Row') }}</th><th>{{ __('Destination') }}</th><th>{{ __('Why') }}</th></tr></thead>
                        <tbody class="text-xs">
                            @foreach ($result['skipped'] as $skip)
                                <tr><td class="tabular-nums">{{ $skip['line'] }}</td><td class="max-w-xs truncate">{{ $skip['destination'] }}</td><td class="text-muted">{{ $skip['reason'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <a href="{{ route('links.index') }}" class="btn-primary btn-sm">{{ __('See my links') }}</a>
        </section>
    @endif

    @if ($preview)
        @php
            $counts = $preview['counts'];
            $ready = ($counts['ok'] ?? 0);
            $dupes = ($counts['duplicate'] ?? 0);
            $chips = ['ok' => ['chip-green', __('Ready')], 'duplicate' => ['chip-amber', __('Duplicate')], 'error' => ['chip-red', __('Problem')], 'quota' => ['chip-gray', __('Over allowance')]];
        @endphp
        <section class="glass-card space-y-4" x-data="{ dupes: false }">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="section-title mr-auto">{{ __('Preview') }}: {{ trans_choice(':count row|:count rows', count($preview['rows']), ['count' => count($preview['rows'])]) }}</h2>
                @foreach ($chips as $status => [$class, $label])
                    @if (! empty($counts[$status]))
                        <span class="{{ $class }}">{{ $label }}: {{ $counts[$status] }}</span>
                    @endif
                @endforeach
            </div>
            <div class="overflow-x-auto">
                <table class="table-glass">
                    <thead><tr><th>{{ __('Row') }}</th><th>{{ __('Destination') }}</th><th>{{ __('Slug') }}</th><th>{{ __('Tags') }}</th><th>{{ __('Folder') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Clicks') }}</th><th>{{ __('Result') }}</th></tr></thead>
                    <tbody class="text-xs">
                        @foreach ($preview['rows'] as $row)
                            <tr>
                                <td class="tabular-nums text-muted">{{ $row['line'] }}</td>
                                <td class="max-w-[16rem] truncate text-ink" title="{{ $row['destination'] }}">{{ $row['destination'] }}</td>
                                <td class="font-mono">{{ $row['slug'] ?: '—' }}</td>
                                <td>{{ implode(', ', $row['tags']) ?: '—' }}</td>
                                <td>{{ $row['folder'] ?: '—' }}</td>
                                <td class="whitespace-nowrap">{{ $row['expires_at'] ?: '—' }}</td>
                                <td class="tabular-nums">{{ $row['max_clicks'] ?? '—' }}</td>
                                <td class="min-w-[12rem]">
                                    <span class="{{ $chips[$row['status']][0] }}">{{ $chips[$row['status']][1] }}</span>
                                    @foreach ($row['problems'] as $problem)
                                        <p class="mt-1 text-muted">{{ $problem }}</p>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('links.import.commit') }}" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="hidden" name="token" value="{{ $preview['token'] }}">
                @if ($dupes > 0)
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="allow_duplicates" value="1" x-model="dupes" class="border-slate-400/50 text-indigo-600 focus:ring-indigo-500">
                        {{ __('Also create the :count duplicate rows', ['count' => $dupes]) }}
                    </label>
                @endif
                @if ($dupes > 0)
                    <button type="submit" class="btn-primary btn-sm" :disabled="{{ $ready }} + (dupes ? {{ $dupes }} : 0) === 0">{{ __('Create the links') }}</button>
                @else
                    <button type="submit" class="btn-primary btn-sm" @disabled($ready === 0)>{{ __('Create the links') }}</button>
                @endif
                <a href="{{ route('links.import') }}" class="btn-ghost btn-sm">{{ __('Start over') }}</a>
                <p class="w-full text-xs text-muted">{{ __('Rows with a problem are skipped. Nothing is created until you press the button.') }}</p>
            </form>
        </section>
    @else
        <div class="grid items-start gap-3 lg:grid-cols-3">
            <form method="POST" action="{{ route('links.import.preview') }}" enctype="multipart/form-data" class="glass-card space-y-3 lg:col-span-2">
                @csrf
                <h2 class="section-title">{{ __('Choose a file') }}</h2>
                <div>
                    <label for="import-file" class="label">{{ __('CSV file (up to :rows rows, 512 KB)', ['rows' => $maxRows]) }}</label>
                    <input id="import-file" type="file" name="file" accept=".csv,text/csv,text/plain" required class="input file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-500/15 file:px-3 file:py-1 file:text-sm file:text-ink">
                </div>
                <button type="submit" class="btn-primary btn-sm">{{ __('Check the file') }}</button>
            </form>
            <section class="glass-card space-y-2">
                <h2 class="section-title">{{ __('Columns') }}</h2>
                <p class="text-xs text-muted">{{ __('The first row names the columns, in any order. Only "destination" is required.') }}</p>
                <dl class="space-y-1.5 text-xs">
                    <div><dt class="font-mono text-ink">destination</dt><dd class="text-muted">{{ __('Where the link goes. https:// is added when missing.') }}</dd></div>
                    <div><dt class="font-mono text-ink">slug</dt><dd class="text-muted">{{ __('Optional. Letters, numbers and hyphens, 3 to 48. Empty makes a random one.') }}</dd></div>
                    <div><dt class="font-mono text-ink">tags</dt><dd class="text-muted">{{ __('Optional. Separate several with ; or , or |. New tags are created.') }}</dd></div>
                    <div><dt class="font-mono text-ink">folder</dt><dd class="text-muted">{{ __('Optional. A new folder is created if needed; empty uses your default folder.') }}</dd></div>
                    <div><dt class="font-mono text-ink">expires_at</dt><dd class="text-muted">{{ __('Optional. A date in the future, like 2027-03-31 23:59.') }}</dd></div>
                    <div><dt class="font-mono text-ink">max_clicks</dt><dd class="text-muted">{{ __('Optional. The link stops after this many clicks.') }}</dd></div>
                </dl>
            </section>
        </div>
    @endif
</x-app-layout>
