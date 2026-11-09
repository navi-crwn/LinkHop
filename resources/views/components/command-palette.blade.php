@php
    $commands = array_values(array_filter([
        ['id' => 'new', 'label' => __('New link'), 'hint' => __('Action'), 'action' => 'new-link', 'href' => route('links.index').'?create=1', 'keywords' => 'create add shorten'],
        ['id' => 'dashboard', 'label' => __('Dashboard'), 'hint' => __('Page'), 'href' => route('dashboard')],
        ['id' => 'links', 'label' => __('My links'), 'hint' => __('Page'), 'href' => route('links.index'), 'keywords' => 'list search'],
        ['id' => 'analytics', 'label' => __('Analytics'), 'hint' => __('Page'), 'href' => route('analytics'), 'keywords' => 'clicks stats'],
        ['id' => 'folders', 'label' => __('Folders'), 'hint' => __('Page'), 'href' => route('folders.index')],
        ['id' => 'tags', 'label' => __('Tags'), 'hint' => __('Page'), 'href' => route('tags.index')],
        bio_enabled() ? ['id' => 'bio', 'label' => __('Link in Bio'), 'hint' => __('Page'), 'href' => route('bio.index')] : null,
        ['id' => 'import', 'label' => __('Import links from CSV'), 'hint' => __('Page'), 'href' => route('links.import'), 'keywords' => 'upload bulk'],
        ['id' => 'webhooks', 'label' => __('Webhooks'), 'hint' => __('Settings'), 'href' => route('settings').'#webhooks', 'keywords' => 'events signature'],
        ['id' => 'settings', 'label' => __('Settings'), 'hint' => __('Page'), 'href' => route('settings'), 'keywords' => 'export backup activity'],
        ['id' => 'api', 'label' => __('API documentation'), 'hint' => __('Page'), 'href' => route('api-docs'), 'keywords' => 'token idempotency'],
        ['id' => 'check', 'label' => __('Check a link'), 'hint' => __('Page'), 'href' => route('link.check'), 'keywords' => 'safe scan'],
        ['id' => 'changelog', 'label' => __('Changelog'), 'hint' => __('Page'), 'href' => route('changelog')],
        ['id' => 'theme', 'label' => __('Switch light or dark theme'), 'hint' => __('Action'), 'action' => 'theme', 'keywords' => 'dark light mode'],
    ]));
@endphp
<div x-data="commandPalette(@js(['commands' => $commands, 'linksUrl' => route('palette.links')]))"
     @keydown.window="onGlobalKey($event)" @open-palette.window="show()"
     @keydown.escape.window="if (open) { $event.stopPropagation(); close(); }">
    <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-start justify-center p-3 pt-[12vh] sm:p-6 sm:pt-[14vh]">
        <div class="absolute inset-0 bg-slate-950/50" @click="close()" aria-hidden="true"></div>
        <div x-ref="dialog" role="dialog" aria-modal="true" aria-label="{{ __('Search and commands') }}"
             @keydown.tab="trap($event)"
             class="glass-strong relative flex max-h-[70vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl">
            <div class="flex items-center gap-2 border-b border-slate-500/15 px-3">
                <svg class="h-4 w-4 shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                <input x-ref="input" x-model="query" @input="onInput()" type="text" autocomplete="off" spellcheck="false"
                       role="combobox" aria-expanded="true" aria-controls="palette-list" aria-autocomplete="list"
                       :aria-activedescendant="items.length ? 'palette-opt-' + active : null"
                       aria-label="{{ __('Search pages and your links') }}"
                       placeholder="{{ __('Search pages and your links…') }}"
                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                       @keydown.enter.prevent="run(null, $event.shiftKey)"
                       data-ctl-max="640" class="h-10 min-w-0 flex-1 rounded-lg border-0 bg-transparent px-0 text-sm text-ink outline-none placeholder:text-muted focus:ring-0 max-sm:h-11 max-sm:text-base">
                <button type="button" @click="close()" class="icon-btn shrink-0" aria-label="{{ __('Close') }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <ul id="palette-list" role="listbox" aria-label="{{ __('Results') }}" class="min-h-0 flex-1 overflow-y-auto p-1.5">
                <template x-for="(item, i) in items" :key="item.key">
                    <li :id="'palette-opt-' + i" role="option" :aria-selected="i === active"
                        @mousemove="active = i" @click="run(item)"
                        class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2 text-sm"
                        :class="i === active ? 'bg-indigo-500/15 text-ink' : 'text-ink'">
                        <span class="min-w-0 flex-1 truncate" :class="item.kind === 'link' && 'font-mono text-xs'" x-text="item.label"></span>
                        <span class="hidden max-w-[45%] truncate text-xs text-muted sm:block" x-text="item.hint"></span>
                    </li>
                </template>
            </ul>
            <p x-show="!items.length && !loading" x-cloak class="px-4 pb-4 text-sm text-muted">{{ __('Nothing found.') }}</p>
            <div class="flex items-center justify-between gap-2 border-t border-slate-500/15 px-3 py-2 text-[11px] text-muted">
                <span class="truncate">{{ __('↑ ↓ to move · Enter to open · Shift Enter edits a link · Esc to close') }}</span>
                <span class="shrink-0 font-mono" x-text="shortcut"></span>
            </div>
        </div>
    </div>
</div>
