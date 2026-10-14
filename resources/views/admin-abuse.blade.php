<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Abuse reports</x-slot>
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Abuse Reports</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Review reported links</p>
    </div>
    <div class="py-4">
        <div class="mx-auto max-w-7xl space-y-4 px-4 md:px-6">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif
            <div class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Automated findings</h2>
                    <span class="text-xs text-gray-500">From <code>abuse:scan</code>. Restore = false positive (won't be re-flagged).</span>
                </div>
                @if ($flags->isEmpty())
                    <p class="px-4 py-6 text-sm text-gray-500">No open findings.</p>
                @else
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                    <thead class="text-left text-gray-500 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Signals</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($flags as $flag)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold">{{ str_replace('_', ' ', $flag->subject_type) }} #{{ $flag->subject_id }}</p>
                                    <p class="text-xs text-gray-500 break-all">{{ $flag->summary }}</p>
                                    <p class="text-xs text-gray-400">{{ $flag->created_at->diffForHumans() }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($flag->isCsam())
                                        <span class="mb-1 inline-block rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">CSAM — do not open, report</span>
                                    @endif
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($flag->signals ?? [] as $signal)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">{{ $signal }}</span>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400">score {{ $flag->score }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $flag->action === 'flagged' ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-200' }}">{{ ucfirst($flag->action) }}</span>
                                    @if ($flag->status === 'confirmed')
                                        <span class="ml-1 text-xs text-emerald-600">confirmed</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    @if ($flag->user)
                                        <a href="{{ route('admin.users.inspect', $flag->user) }}" class="text-blue-600 hover:underline">{{ $flag->user->email }}</a>
                                        <span class="block text-gray-400">{{ $flag->user->status }}</span>
                                    @else
                                        <span class="text-gray-400">guest</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if ($flag->status === 'open')
                                        <form method="POST" action="{{ route('admin.abuse.flags.confirm', $flag) }}" class="inline">
                                            @csrf
                                            <button class="rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold text-white hover:bg-slate-700">Confirm</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.abuse.flags.restore', $flag) }}" class="inline" data-confirm="Restore this item and stop flagging it?">
                                        @csrf
                                        <button class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Restore</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <div class="mt-4">{{ $flags->links() }}</div>
                @endif
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-white">User reports</h2>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                    <thead class="text-left text-gray-500 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3">Slug / URL</th>
                            <th class="px-4 py-3">Reason</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($reports as $report)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold">{{ $report->slug ?? 'n/a' }}</p>
                                    <p class="text-xs text-gray-500 break-all">{{ $report->url }}</p>
                                    <p class="text-xs text-gray-400">IP: {{ $report->ip_address }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $report->reason }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ ucfirst($report->status) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.abuse.update', $report) }}" class="inline-flex gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="rounded-full border-gray-200 bg-white px-3 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                            @foreach (['open','investigating','closed'] as $option)
                                                <option value="{{ $option }}" @selected($report->status === $option)>{{ ucfirst($option) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="rounded-full bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-500">Update</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-4">
                    {{ $reports->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
