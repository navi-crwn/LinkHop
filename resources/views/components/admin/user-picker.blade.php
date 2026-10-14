@props(['selected' => null, 'name' => 'user_id', 'placeholder' => 'Any owner'])
{{-- Searchable owner filter: loads at most 10 matches from admin.users.lookup instead of rendering every user. --}}
<div class="relative" x-data="{
        open: false, term: '', results: [], loading: false,
        selected: @js($selected ? ['id' => $selected->id, 'name' => $selected->name, 'email' => $selected->email] : null),
        async search() {
            this.loading = true;
            try {
                const res = await fetch(@js(route('admin.users.lookup')) + '?q=' + encodeURIComponent(this.term), { headers: { 'Accept': 'application/json' } });
                this.results = res.ok ? await res.json() : [];
            } finally { this.loading = false; }
        },
        pick(user) { this.selected = user; this.open = false; this.term = ''; this.$nextTick(() => this.$refs.input.form?.requestSubmit()); },
        clear() { this.selected = null; this.$nextTick(() => this.$refs.input.form?.requestSubmit()); },
    }" @click.outside="open = false">
    <input type="hidden" name="{{ $name }}" :value="selected ? selected.id : ''" x-ref="input">
    <template x-if="selected">
        <div class="input flex items-center justify-between gap-2 !py-2">
            <span class="min-w-0 truncate"><span class="font-medium" x-text="selected.name"></span> <span class="text-xs text-muted" x-text="selected.email"></span></span>
            <button type="button" @click="clear()" class="shrink-0 text-muted hover:text-ink" aria-label="Clear owner">&times;</button>
        </div>
    </template>
    <template x-if="!selected">
        <input type="search" x-model="term" @focus="open = true; if (!results.length) search()" @input.debounce.250ms="open = true; search()"
               @keydown.escape="open = false" placeholder="{{ $placeholder }}" aria-label="Owner" autocomplete="off" class="input">
    </template>
    <div x-show="open && !selected" x-cloak class="glass-strong absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-xl p-1 shadow-lg">
        <p x-show="loading" class="px-3 py-2 text-xs text-muted">Searching…</p>
        <p x-show="!loading && !results.length" class="px-3 py-2 text-xs text-muted">No users found.</p>
        <template x-for="user in results" :key="user.id">
            <button type="button" @click="pick(user)" class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-500/10">
                <span class="block truncate font-medium text-ink" x-text="user.name"></span>
                <span class="block truncate text-xs text-muted" x-text="user.email"></span>
            </button>
        </template>
    </div>
</div>
