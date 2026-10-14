@if (session('status') || session('success'))
    <div class="glass flex items-center gap-2 rounded-2xl px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">
        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        {{ session('status') ?? session('success') }}
    </div>
@endif
@if ($errors->any())
    <div class="glass rounded-2xl border-rose-400/40 px-4 py-3 text-sm text-rose-700 dark:text-rose-300">
        <ul class="list-disc space-y-0.5 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
