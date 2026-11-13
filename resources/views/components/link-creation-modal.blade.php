@props(['folders', 'tags', 'isAdmin' => false])
<div
    x-data="linkCreationModal()"
    x-init="init()"
    x-cloak
    x-show="panel"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[60] flex items-center justify-center p-4"
    {{-- Escape closes the innermost layer only: an option sheet, then an open folder/tag list, then the modal itself. --}}
    @keydown.escape.window="if (sheet) { closeSheet(); } else if (showFolderModal) { showFolderModal = false; } else if (showTagModal) { showTagModal = false; } else if (activeDropdown) { activeDropdown = null; tagSearch = ''; folderSearch = ''; } else if (panel) { closePanel(); }"
>
    <div @click="if (!sheet && !showFolderModal && !showTagModal) { closePanel(); }" class="absolute inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
    <div
        x-show="!sheet && !showFolderModal && !showTagModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative z-10 w-full max-w-5xl max-h-[90vh] rounded-2xl glass-strong glass-popover no-skin flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('Create new link') }}"
    >
        <div x-show="createdLink" class="flex flex-col h-full">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4 sm:px-6 dark:border-slate-800">
                <div>
                    <h3 class="text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ __('Link created') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Your short link is ready to use') }}</p>
                </div>
                <button type="button" aria-label="{{ __('Close') }}" @click="closePanel()" class="icon-btn">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                <div class="max-w-2xl mx-auto space-y-4">
                    <div class="rounded-xl border-2 border-emerald-300 bg-emerald-50 p-4 sm:p-5 dark:border-emerald-800 dark:bg-emerald-900/20">
                        <label for="flinkcr-1" class="label">{{ __('Your short URL') }}</label>
                        {{-- One column on a phone: the input keeps its full width and the Copy button stays on screen. --}}
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                            <input id="flinkcr-1"
                                type="text"
                                :value="createdLink?.short_url"
                                readonly
                                class="input input-lg min-w-0 flex-1 font-mono font-semibold"
                            >
                            <button
                                type="button"
                                data-copied="{{ __('Copied') }}"
                                data-failed="{{ __('Copy failed') }}"
                                @click="hopCopy(createdLink?.short_url || '', $el)"
                                class="btn-primary btn-lg"
                            >
                                📋 {{ __('Copy') }}
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" x-show="createdLink?.qr_code_url">
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">{{ __('QR Code') }}</h4>
                        <div class="flex flex-col items-start gap-4 sm:flex-row">
                            <div class="shrink-0">
                                <img :src="createdLink?.qr_code_url" alt="{{ __('QR Code') }}" class="w-[150px] h-[150px] object-contain rounded-lg border border-slate-200 dark:border-slate-700 bg-white">
                            </div>
                            <div class="min-w-0 flex-1 space-y-4">
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Download your custom QR code as PNG, JPG, or SVG') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <a 
                                        :href="`/links/${createdLink?.id}/qr/png`" 
                                        download 
                                        class="btn-soft btn-dense"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        PNG
                                    </a>
                                    <a 
                                        :href="`/links/${createdLink?.id}/qr/jpg`" 
                                        download 
                                        class="btn-soft btn-dense"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        JPG
                                    </a>
                                    <a 
                                        :href="`/links/${createdLink?.id}/qr/svg`" 
                                        download 
                                        class="btn-soft btn-dense"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        SVG
                                    </a>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('SVG stays sharp when resized for printing') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button
                            type="button"
                            @click="resetForm()"
                            class="btn-outline"
                        >
                            {{ __('Create another link') }}
                        </button>
                        <button
                            type="button"
                            @click="closePanel()"
                            class="btn-primary"
                        >
                            {{ __('View dashboard') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        {{-- The form wraps both columns, so on a phone the folder and preview come before the Create button
             (one column below lg) instead of after it. --}}
        <form x-show="!createdLink" @submit.prevent="submitForm()" class="flex min-h-0 flex-col overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4 sm:px-6 dark:border-slate-800">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Create new link') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Shorten and customize your URL') }}</p>
                </div>
                <button type="button" aria-label="{{ __('Close') }}" @click="closePanel()" class="icon-btn">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden">
                <div class="grid lg:grid-cols-2">
                    <div class="min-w-0 lg:border-r lg:border-slate-200 dark:lg:border-slate-800">
                        <div class="space-y-5 p-4 sm:p-6">
                            @csrf
                            <div x-show="!formData.wa_mode">
                                <label class="label">
                                    {{ __('Destination URL') }}
                                    <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    id="modal-target-url"
                                    type="url" 
                                    x-model="formData.target_url"
                                    @input="error = ''; debouncedFetchOg(); if(!formData.slug) { debouncedGenerateSlug(); }"
                                    @paste="$nextTick(() => { clearTimeout(ogFetchTimeout); fetchOgData(); })"
                                    placeholder="https://example.com"
                                    x-bind:required="!formData.wa_mode"
                                    class="input"
                                >
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Enter the long URL you want to shorten') }}</p>
                                {{-- Preview of the page you pasted (the same lookup as the social card) --}}
                                <div x-show="ogLoading || ogData.title" x-cloak class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800/60" aria-live="polite">
                                    <p x-show="ogLoading && !ogData.title" class="text-xs text-slate-500 dark:text-slate-400">{{ __('Looking up the page…') }}</p>
                                    <p x-show="ogData.title" class="truncate text-sm font-medium text-slate-800 dark:text-slate-100" x-text="ogData.title"></p>
                                    <p x-show="ogData.description" class="line-clamp-2 text-xs text-slate-500 dark:text-slate-400" x-text="ogData.description"></p>
                                </div>
                            </div>
                            <div>
                                <label class="label">
                                    {{ __('Short Link') }}
                                </label>
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        x-model="formData.slug"
                                        @input="generateQrPreview()"
                                        @input.debounce.350ms="slugCheck.run(formData.slug)"
                                        placeholder="custom-slug"
                                        pattern="[A-Za-z0-9\-]{3,48}" title="{{ __('Slug can only contain letters, numbers, and hyphens.') }}"
                                        class="input min-w-0 flex-1"
                                    >
                                    <button 
                                        type="button"
                                        @click="generateRandomSlug()"
                                        class="btn-outline"
                                    >
                                        🎲 {{ __('Random') }}
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Leave empty to generate a slug automatically') }}</p>
                                <p x-show="slugCheck.state === 'ok' || slugCheck.state === 'bad'" x-cloak x-text="slugCheck.message" role="status" aria-live="polite"
                                   class="mt-1 text-xs" :class="slugCheck.state === 'ok' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"></p>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <label class="label mb-0!">
                                            {{ __('Tags') }}
                                        </label>
                                        <span x-show="tagSuccess" x-transition class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('Tag created.') }}</span>
                                    </div>
                                    <button 
                                        type="button"
                                        @click="showTagModal = true"
                                        class="btn-link" data-ctl="link"
                                    >
                                        {{ __('+ New tag') }}
                                    </button>
                                </div>
                                {{-- Escape here closes only this list (see the window handler above). --}}
                                <div class="relative" @click.outside="if (activeDropdown === 'tags') { activeDropdown = null; tagSearch = ''; }">
                                    <button
                                        type="button"
                                        @click="activeDropdown = activeDropdown === 'tags' ? null : 'tags'; tagSearch = ''"
                                        class="input flex items-center justify-between gap-2 text-left" data-ctl="select"
                                    >
                                        <span class="text-sm" x-text="tagsLabel()"></span>
                                        <svg class="w-4 h-4 transition-transform" :class="activeDropdown === 'tags' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div 
                                        x-show="activeDropdown === 'tags'"
                                        x-transition
                                        class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg shadow-xl overflow-hidden"
                                    >
                                        <div class="p-2 border-b border-slate-200 dark:border-slate-700">
                                            <input 
                                                type="text" 
                                                x-model="tagSearch"
                                                autocomplete="new-password"
                                                placeholder="{{ __('Search tags...') }}"
                                                aria-label="{{ __('Search tags') }}"
                                                class="input input-dense"
                                                @click.stop
                                                @keydown.enter.prevent
                                            >
                                        </div>
                                        <div class="overflow-y-auto" style="max-height: 160px;">
                                            <template x-if="tags.length === 0">
                                                <p class="text-xs text-slate-500 dark:text-slate-400 text-center py-4">{{ __('No tags yet') }}</p>
                                            </template>
                                            <template x-for="tag in tags.filter(t => !tagSearch || t.name.toLowerCase().includes(tagSearch.toLowerCase()))" :key="tag.id">
                                                <label class="option">
                                                    <span class="text-sm text-slate-700 dark:text-slate-300" x-text="tag.name"></span>
                                                    <input
                                                        type="checkbox"
                                                        :value="tag.id"
                                                        :aria-label="tag.name"
                                                        :checked="formData.tags.includes(tag.id)"
                                                        @change="
                                                            if ($event.target.checked) {
                                                                if (!formData.tags.includes(tag.id)) formData.tags.push(tag.id);
                                                            } else {
                                                                formData.tags = formData.tags.filter(id => id !== tag.id);
                                                            }
                                                        "
                                                        class="border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600"
                                                    >
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- Every other option lives in its own small sheet (dub.co style): the core dialog stays
                                 short, and a chip that was filled shows a short summary of what is inside. --}}
                            <div>
                                <p class="label">{{ __('Link options') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="openSheet('utm', $el)" data-chip="utm"
                                        class="btn-soft btn-dense inline-flex items-center gap-1.5"
                                        :class="chipSummary('utm') ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : ''">
                                        <span class="text-sm">{{ __('Campaign tags (UTM)') }}</span>
                                        <span x-show="chipSummary('utm')" x-cloak x-text="chipSummary('utm')" class="rounded-full bg-emerald-600/15 px-1.5 text-[11px] font-semibold"></span>
                                    </button>
                                    <button type="button" @click="openSheet('qr', $el)" data-chip="qr"
                                        class="btn-soft btn-dense inline-flex items-center gap-1.5"
                                        :class="chipSummary('qr') ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : ''">
                                        <span class="text-sm">{{ __('QR code') }}</span>
                                        <span x-show="chipSummary('qr')" x-cloak x-text="chipSummary('qr')" class="rounded-full bg-emerald-600/15 px-1.5 text-[11px] font-semibold"></span>
                                    </button>
                                    <button type="button" @click="openSheet('og', $el)" data-chip="og"
                                        class="btn-soft btn-dense inline-flex items-center gap-1.5"
                                        :class="chipSummary('og') ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : ''">
                                        <span class="text-sm">{{ __('Social preview') }}</span>
                                        <span x-show="chipSummary('og')" x-cloak x-text="chipSummary('og')" class="rounded-full bg-emerald-600/15 px-1.5 text-[11px] font-semibold"></span>
                                    </button>
                                    <button type="button" @click="openSheet('schedule', $el)" data-chip="schedule"
                                        class="btn-soft btn-dense inline-flex items-center gap-1.5"
                                        :class="chipSummary('schedule') ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : ''">
                                        <span class="text-sm">{{ __('Schedule & limits') }}</span>
                                        <span x-show="chipSummary('schedule')" x-cloak x-text="chipSummary('schedule')" class="rounded-full bg-emerald-600/15 px-1.5 text-[11px] font-semibold"></span>
                                    </button>
                                    <button type="button" @click="openSheet('protect', $el)" data-chip="protect"
                                        class="btn-soft btn-dense inline-flex items-center gap-1.5"
                                        :class="chipSummary('protect') ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : ''">
                                        <span class="text-sm">{{ __('Protection') }}</span>
                                        <span x-show="chipSummary('protect')" x-cloak x-text="chipSummary('protect')" class="rounded-full bg-emerald-600/15 px-1.5 text-[11px] font-semibold"></span>
                                    </button>
                                    <button type="button" @click="openSheet('app', $el)" data-chip="app"
                                        class="btn-soft btn-dense inline-flex items-center gap-1.5"
                                        :class="chipSummary('app') ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : ''">
                                        <span class="text-sm">{{ __('Open in the app') }}</span>
                                        <span x-show="chipSummary('app')" x-cloak x-text="chipSummary('app')" class="rounded-full bg-emerald-600/15 px-1.5 text-[11px] font-semibold"></span>
                                    </button>
                                    <button type="button" @click="openSheet('routing', $el)" data-chip="routing"
                                        class="btn-soft btn-dense inline-flex items-center gap-1.5"
                                        :class="chipSummary('routing') ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : ''">
                                        <span class="text-sm">{{ __('Routing and split') }}</span>
                                        <span x-show="chipSummary('routing')" x-cloak x-text="chipSummary('routing')" class="rounded-full bg-emerald-600/15 px-1.5 text-[11px] font-semibold"></span>
                                    </button>
                                    <button type="button" @click="openSheet('wa', $el)" data-chip="wa"
                                        class="btn-soft btn-dense inline-flex items-center gap-1.5"
                                        :class="chipSummary('wa') ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : ''">
                                        <span class="text-sm">{{ __('WhatsApp rotator') }}</span>
                                        <span x-show="chipSummary('wa')" x-cloak x-text="chipSummary('wa')" class="rounded-full bg-emerald-600/15 px-1.5 text-[11px] font-semibold"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="min-w-0 bg-white dark:bg-slate-800">
                        {{-- Two columns share one scroller: on lg the folder and preview stay in view (sticky) while the
                             options on the left scroll, and scroll on their own when they are taller than the scroller
                             (90vh minus the header and the footer, about 9.25rem). One column below lg: no sticky. --}}
                        <div class="space-y-6 p-4 sm:p-6 lg:sticky lg:top-0 lg:max-h-[calc(90vh-10rem)] lg:overflow-y-auto lg:overscroll-contain">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <label class="label mb-0!">
                                            {{ __('Folder') }}
                                        </label>
                                        <span x-show="folderSuccess" x-transition class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('Folder created.') }}</span>
                                    </div>
                                    <button 
                                        type="button"
                                        @click="showFolderModal = true"
                                        class="btn-link" data-ctl="link"
                                    >
                                        {{ __('+ New folder') }}
                                    </button>
                                </div>
                                <div class="relative" @click.outside="if (activeDropdown === 'folders') { activeDropdown = null; folderSearch = ''; }">
                                    <button
                                        type="button"
                                        @click="activeDropdown = activeDropdown === 'folders' ? null : 'folders'; folderSearch = ''"
                                        class="input flex items-center justify-between gap-2 text-left" data-ctl="select"
                                    >
                                        <span class="text-sm" x-text="folders.find(f => f.id == formData.folder_id)?.name || @js(__('Select folder'))"></span>
                                        <svg class="w-4 h-4 transition-transform" :class="activeDropdown === 'folders' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div 
                                        x-show="activeDropdown === 'folders'"
                                        x-transition
                                        class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg shadow-xl overflow-hidden"
                                    >
                                        <div class="p-2 border-b border-slate-200 dark:border-slate-700">
                                            <input 
                                                type="text" 
                                                x-model="folderSearch"
                                                autocomplete="new-password"
                                                placeholder="{{ __('Search folders...') }}"
                                                aria-label="{{ __('Search folders') }}"
                                                class="input input-dense"
                                                @click.stop
                                                @keydown.enter.prevent
                                            >
                                        </div>
                                        <div class="overflow-y-auto" style="max-height: 160px;">
                                            <template x-for="folder in folders.filter(f => !folderSearch || f.name.toLowerCase().includes(folderSearch.toLowerCase()))" :key="folder.id">
                                                <button
                                                    type="button"
                                                    @click="formData.folder_id = folder.id; activeDropdown = null; folderSearch = ''"
                                                    class="option" data-ctl="option"
                                                    :class="formData.folder_id == folder.id ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-slate-700 dark:text-slate-300'"
                                                >
                                                    <span class="text-sm" x-text="folder.name"></span>
                                                </button>
                                            </template>
                                            <template x-if="folders.filter(f => !folderSearch || f.name.toLowerCase().includes(folderSearch.toLowerCase())).length === 0">
                                                <p class="text-xs text-slate-500 dark:text-slate-400 text-center py-4">{{ __('No folders found') }}</p>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- QR preview: the "QR code" chip switches it on, this shows what will be generated. --}}
                            <div x-show="qrSettings.generateQr" x-cloak>
                                <div class="flex items-center justify-between mb-3">
                                    <label class="label mb-0!">
                                        {{ __('QR code preview') }}
                                    </label>
                                    <button 
                                        type="button"
                                        @click="openSheet('qr', $el)"
                                        class="btn-ghost btn-dense"
                                    >
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                        {{ __('Customize QR') }}
                                    </button>
                                </div>
                                <div class="rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-600 p-4 text-center bg-white dark:bg-slate-800" :style="'background-color: ' + qrSettings.bgColor">
                                    <div x-show="formData.slug" class="space-y-2">
                                        <div class="relative inline-block">
                                            {{-- The code is drawn on the server (about a second): say so instead of showing an empty box. --}}
                                            <img :src="qrPreviewUrl" alt="{{ __('QR Preview') }}" loading="eager" @load="qrPreviewLoading = false" x-on:error="qrPreviewLoading = false" class="w-32 h-32 mx-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                            <div x-show="qrPreviewLoading" x-cloak class="absolute inset-0 flex items-center justify-center rounded-lg bg-slate-100/90 text-xs text-slate-500 dark:bg-slate-800/90 dark:text-slate-300" role="status">{{ __('Generating…') }}</div>
                                            <div x-show="qrSettings.showLogo && qrSettings.logo" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                                <div class="w-11 h-11 rounded-lg bg-white dark:bg-slate-900 p-1 shadow-lg border border-slate-200 dark:border-slate-700">
                                                    <img :src="qrSettings.logo" alt="{{ __('Logo') }}" class="w-full h-full object-contain rounded-sm">
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('QR will be generated after link creation') }}</p>
                                    </div>
                                    <div x-show="!formData.slug" class="py-8">
                                        <svg class="w-16 h-16 mx-auto text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                        </svg>
                                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ __('Enter a slug to preview the QR code') }}</p>
                                    </div>
                                </div>
                            </div>
                            {{-- Compact social card: the platform tabs and the custom fields live in the "Social preview" sheet. --}}
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <label class="label mb-0!">
                                        {{ __('Link Preview') }}
                                    </label>
                                    <button 
                                        type="button"
                                        @click="openSheet('og', $el)"
                                        class="btn-ghost btn-dense"
                                    >
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                        {{ __('Custom Preview') }}
                                    </button>
                                </div>
                                <div class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-800">
                                    <div x-show="ogLoading && !ogData.title" class="p-8 text-center">
                                        <svg class="motion-safe:animate-spin h-8 w-8 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Fetching preview…') }}</p>
                                    </div>
                                    <div x-show="!ogLoading || ogData.title">
                                        {{-- Sized by aspect ratio, never wider than the column (a fixed 400px made the modal scroll sideways on a phone).
                                             An image that fails to load hides itself instead of showing the browser's broken-image icon. --}}
                                        <div x-show="ogData.image" class="mx-auto flex w-full max-w-[400px] items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-900" style="aspect-ratio: 1200 / 630">
                                            <img :src="ogData.image" :alt="ogData.title" x-on:error="$el.style.visibility = 'hidden'" @load="$el.style.visibility = ''" class="max-w-full max-h-full object-contain">
                                        </div>
                                        <div class="p-4">
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white line-clamp-2" x-text="ogData.title || @js(__('Link Title'))"></p>
                                            <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 line-clamp-3" x-text="ogData.description || @js(__('Link description will appear here when you enter a URL or add custom text'))"></p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">hel.ink</p>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-center text-slate-500 dark:text-slate-400">{{ __('How your link will appear on social platforms') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-2 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-end sm:px-6 dark:border-slate-800">
                <p x-show="error" x-cloak x-text="error" role="alert" class="min-w-0 rounded-xl bg-rose-500/10 px-3 py-2 text-sm text-rose-700 sm:mr-auto dark:text-rose-300"></p>
                <button
                    type="submit"
                    :disabled="isLoading || (!formData.target_url && !formData.wa_mode)"
                    class="btn-primary btn-lg max-sm:w-full"
                >
                    <span x-show="!isLoading">{{ __('Create Link') }}</span>
                    <span x-show="isLoading" class="flex items-center justify-center gap-2">
                        <svg class="motion-safe:animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Creating...') }}
                    </span>
                </button>
            </div>
        </form>
    </div>
    {{-- Option sheets: one small dialog per option group, all on the same layer (z-200/z-210), all closed by
         Escape through the chain above, all rendered with x-show so their fields stay in the DOM. --}}
    <div
        x-show="sheet === 'utm'"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="closeSheet()"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            data-sheet="utm"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Campaign tags (UTM)') }}"
            class="relative z-210 flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-2xl dark:bg-slate-800"
        >
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Campaign tags (UTM)') }}</h4>
                <button type="button" aria-label="{{ __('Close') }}" @click="closeSheet()" class="icon-btn">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-5">
                <x-utm-builder target="modal-target-url" :plain="true" />
            </div>
            <div class="form-actions border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                <button type="button" @click="closeSheet()" class="btn-primary">{{ __('Done') }}</button>
            </div>
        </div>
    </div>
    <div
        x-show="sheet === 'schedule'"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="closeSheet()"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            data-sheet="schedule"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Schedule & limits') }}"
            class="relative z-210 flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-2xl dark:bg-slate-800"
        >
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Schedule & limits') }}</h4>
                <button type="button" aria-label="{{ __('Close') }}" @click="closeSheet()" class="icon-btn">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <div>
                    <label class="label">{{ __('Starts at') }} <span class="font-normal text-slate-400">(UTC)</span></label>
                    <input type="datetime-local" x-model="formData.starts_at" class="input">
                    <p class="mt-1 text-xs text-slate-500">{{ __('Until then the link says it is not live yet.') }}</p>
                </div>
                <div>
                    <label class="label">
                        {{ __('Expires at') }} <span class="font-normal text-slate-400">(UTC)</span>
                    </label>
                    <input
                        type="datetime-local"
                        x-model="formData.expires_at"
                        class="input"
                    >
                </div>
                <div>
                    <label class="label">{{ __('Stop after this many clicks') }}</label>
                    <input type="number" min="1" max="10000000" inputmode="numeric" x-model="formData.max_clicks" placeholder="{{ __('Leave empty for no limit') }}" class="input">
                    <p class="mt-1 text-xs text-slate-500">{{ __('Visits by crawlers and link-preview bots are not counted.') }}</p>
                </div>
                <div>
                    <label class="label">{{ __('When the link has ended, send visitors to') }}</label>
                    <input type="url" x-model="formData.fallback_url" placeholder="https://" class="input">
                    <p class="mt-1 text-xs text-slate-500">{{ __('Used after the expiry date or when the clicks run out. Empty shows a notice.') }}</p>
                </div>
            </div>
            <div class="form-actions border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                <button type="button" @click="resetSchedule()" class="btn-outline">{{ __('Reset') }}</button>
                <button type="button" @click="closeSheet()" class="btn-primary">{{ __('Done') }}</button>
            </div>
        </div>
    </div>
    <div
        x-show="sheet === 'protect'"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="closeSheet()"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            data-sheet="protect"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Protection') }}"
            class="relative z-210 flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-2xl dark:bg-slate-800"
        >
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Protection') }}</h4>
                <button type="button" aria-label="{{ __('Close') }}" @click="closeSheet()" class="icon-btn">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <div>
                    <label class="label">
                        {{ __('Password protection') }}
                    </label>
                    <input 
                        type="password" 
                        x-model="formData.password"
                        placeholder="{{ __('Leave empty for no password') }}"
                        class="input"
                    >
                </div>
                <div>
                    <label class="label">
                        {{ __('Redirect type') }}
                    </label>
                    <select 
                        x-model="formData.redirect_type"
                        class="input"
                    >
                        <option value="302">302 ({{ __('Temporary') }})</option>
                        <option value="301">301 ({{ __('Permanent') }})</option>
                        <option value="307">307 ({{ __('Temporary, keep method') }})</option>
                        <option value="308">308 ({{ __('Permanent, keep method') }})</option>
                    </select>
                    <p x-show="formData.redirect_type === '301' || formData.redirect_type === '308'" x-cloak class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ __('Browsers remember a permanent redirect: visitors who already opened the link keep going to the old address if you change it. A link with a warning, a password, a rule, a limit or a start or end time answers with a temporary redirect instead.') }}</p>
                </div>
                <x-content-warning-fields :model="true" prefix="modal-warning" />
                <div>
                    <label class="label">
                        {{ __('Comments') }}
                    </label>
                    <textarea 
                        x-model="formData.comment"
                        rows="3"
                        placeholder="{{ __('Add internal notes...') }}"
                        class="input"
                    ></textarea>
                </div>
            </div>
            <div class="form-actions border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                <button type="button" @click="resetProtection()" class="btn-outline">{{ __('Reset') }}</button>
                <button type="button" @click="closeSheet()" class="btn-primary">{{ __('Done') }}</button>
            </div>
        </div>
    </div>
    <div
        x-show="sheet === 'app'"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="closeSheet()"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            data-sheet="app"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Open in the app') }}"
            class="relative z-210 flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-2xl dark:bg-slate-800"
        >
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Open in the app') }}</h4>
                <button type="button" aria-label="{{ __('Close') }}" @click="closeSheet()" class="icon-btn">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                <x-link-app-fields :model="true" />
                <x-link-store-fields :model="true" />
            </div>
            <div class="form-actions border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                <button type="button" @click="closeSheet()" class="btn-primary">{{ __('Done') }}</button>
            </div>
        </div>
    </div>
    <div
        x-show="sheet === 'routing'"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="closeSheet()"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            data-sheet="routing"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Routing and split') }}"
            class="relative z-210 flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-2xl dark:bg-slate-800"
        >
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Routing and split') }}</h4>
                <button type="button" aria-label="{{ __('Close') }}" @click="closeSheet()" class="icon-btn">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-5">
                <x-link-routing-fields :model="true" />
            </div>
            <div class="form-actions border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                <button type="button" @click="closeSheet()" class="btn-primary">{{ __('Done') }}</button>
            </div>
        </div>
    </div>
    <div
        x-show="sheet === 'wa'"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="closeSheet()"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            data-sheet="wa"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('WhatsApp rotator') }}"
            class="relative z-210 flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-2xl dark:bg-slate-800"
        >
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('WhatsApp rotator') }}</h4>
                <button type="button" aria-label="{{ __('Close') }}" @click="closeSheet()" class="icon-btn">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-5">
                <x-link-whatsapp-fields :model="true" />
            </div>
            <div class="form-actions border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                <button type="button" @click="closeSheet()" class="btn-primary">{{ __('Done') }}</button>
            </div>
        </div>
    </div>
    <div
        x-show="sheet === 'og'"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="closeSheet()"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            data-sheet="og"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Custom link preview') }}"
            class="relative z-210 flex w-full max-w-4xl max-h-[85vh] flex-col overflow-hidden rounded-xl bg-white dark:bg-slate-800 shadow-2xl"
        >
            <div class="flex shrink-0 items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4 bg-white dark:bg-slate-800">
                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Custom link preview') }}</h4>
                <button type="button" aria-label="{{ __('Close') }}" @click="closeSheet()" class="icon-btn">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            {{-- One column below sm: two narrow columns hid the Save button behind the preview card on a phone. --}}
            <div class="grid min-h-0 flex-1 gap-6 overflow-y-auto p-4 sm:grid-cols-2 sm:p-6">
                <div class="min-w-0 space-y-4">
                    <div>
                        <label class="label">
                            {{ __('Image URL') }}
                        </label>
                        <input 
                            type="url" 
                            x-model="ogData.image"
                            placeholder="https://example.com/image.jpg"
                            class="input"
                        >
                    </div>
                    <div>
                        <label class="label">
                            {{ __('Title') }} <span class="text-slate-500 font-normal text-xs">(<span x-text="ogData.title.length"></span>/120)</span>
                        </label>
                        <input 
                            type="text" 
                            x-model="ogData.title"
                            maxlength="120"
                            placeholder="{{ __('Enter a custom title') }}"
                            class="input"
                        >
                    </div>
                    <div>
                        <label class="label">
                            {{ __('Description') }} <span class="text-slate-500 font-normal text-xs">(<span x-text="ogData.description.length"></span>/240)</span>
                        </label>
                        <textarea 
                            x-model="ogData.description"
                            maxlength="240"
                            rows="4"
                            placeholder="{{ __('Enter a custom description') }}"
                            class="input"
                        ></textarea>
                    </div>
                    <div class="form-actions pt-4">
                        <button 
                            type="button"
                            @click="resetOgData()"
                            class="btn-outline"
                        >
                            {{ __('Reset') }}
                        </button>
                        <button 
                            type="button"
                            @click="closeSheet()"
                            class="btn-primary"
                        >
                            {{ __('Done') }}
                        </button>
                    </div>
                </div>
                <div class="min-w-0 space-y-3">
                    <div class="grid grid-cols-2 gap-1 rounded-lg bg-slate-100 p-1 sm:grid-cols-4 dark:bg-slate-700" role="tablist" aria-label="{{ __('Preview platform') }}">
                        @foreach (['default' => '🌐 '.__('Default'), 'twitter' => '𝕏 Twitter', 'linkedin' => '💼 LinkedIn', 'facebook' => '📘 Facebook'] as $platform => $platformLabel)
                            <button
                                type="button"
                                role="tab"
                                @click="previewPlatform = '{{ $platform }}'"
                                :aria-selected="previewPlatform === '{{ $platform }}' ? 'true' : 'false'"
                                :class="previewPlatform === '{{ $platform }}' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                                class="tab truncate" data-ctl="tab"
                            >
                                {{ $platformLabel }}
                            </button>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Live Preview') }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400" x-text="getPreviewSize(previewPlatform).label"></p>
                    </div>
                    <div class="rounded-xl border-2 border-slate-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-900">
                        <div class="mx-auto flex w-full max-w-[450px] items-center justify-center bg-slate-100 dark:bg-slate-900" :style="`aspect-ratio: ${getPreviewSize(previewPlatform).width} / ${getPreviewSize(previewPlatform).height}`">
                            <img
                                x-show="ogData.image"
                                :src="ogData.image"
                                alt="{{ __('Preview') }}"
                                x-on:error="$el.style.visibility = 'hidden'" @load="$el.style.visibility = ''"
                                class="max-w-full max-h-full object-contain"
                            >
                            <div x-show="!ogData.image" class="text-slate-400 dark:text-slate-600 text-sm">
                                {{ __('No image') }}
                            </div>
                        </div>
                        <div class="p-4 bg-white dark:bg-slate-800">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white line-clamp-2 mb-2" x-text="ogData.title || @js(__('Link Title'))"></p>
                            <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-3" x-text="ogData.description || @js(__('Link description will appear here'))"></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">hel.ink</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div
        x-show="sheet === 'qr'"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="closeSheet()"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            data-sheet="qr"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('QR code design') }}"
            class="relative z-210 flex w-full max-w-md max-h-[85vh] flex-col rounded-2xl bg-white dark:bg-slate-800 shadow-2xl overflow-hidden"
        >
            <div class="flex shrink-0 items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                <div>
                    <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('QR code design') }}</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Customize your QR code appearance') }}</p>
                </div>
                <button type="button" aria-label="{{ __('Close') }}" @click="closeSheet()" class="icon-btn">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200 px-3 py-2.5 dark:border-slate-700">
                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ __('Generate QR code') }}</span>
                    <input type="checkbox" x-model="qrSettings.generateQr" data-qr-toggle class="h-5 w-5 shrink-0 rounded border-slate-300 accent-blue-600">
                </label>
                <div class="text-center">
                    <label class="label">{{ __('Preview') }}</label>
                    <div class="relative inline-block">
                        <div class="w-32 h-32 mx-auto rounded-xl border-2 border-slate-200 dark:border-slate-700 p-2" :style="'background-color: ' + qrSettings.bgColor">
                            <div class="relative w-full h-full flex items-center justify-center" x-effect="generateQrUrl(); qrEditorLoading = !!formData.slug">
                                <img
                                    :src="generateQrUrl()"
                                    alt="{{ __('QR Preview') }}"
                                    loading="eager"
                                    @load="qrEditorLoading = false" x-on:error="qrEditorLoading = false"
                                    class="w-full h-full object-contain"
                                    x-show="formData.slug"
                                >
                                <div x-show="qrEditorLoading && formData.slug" x-cloak class="absolute inset-0 flex items-center justify-center rounded-lg bg-white/80 text-xs text-slate-500 dark:bg-slate-900/80 dark:text-slate-300" role="status">{{ __('Generating…') }}</div>
                                <div x-show="!formData.slug" class="text-slate-500 dark:text-slate-400 text-sm">
                                    {{ __('Enter slug first') }}
                                </div>
                                <div x-show="qrSettings.showLogo && qrSettings.logo" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div class="w-10 h-10 rounded-lg bg-white dark:bg-slate-900 p-1 shadow-lg border border-slate-200 dark:border-slate-700">
                                        <img :src="qrSettings.logo" alt="{{ __('Logo') }}" class="w-full h-full object-contain rounded-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="label">
                        {{ __('QR Code Color') }}
                    </label>
                    <div class="flex items-center gap-2 mb-3 flex-wrap">
                        <template x-for="color in qrColorPresets" :key="color">
                            <button :aria-label="@js(__('QR color')) + ' ' + color"
                                type="button"
                                @click="qrSettings.fgColor = color"
                                :class="qrSettings.fgColor === color ? 'ring-2 ring-offset-2 ring-blue-500 dark:ring-offset-slate-800' : ''"
                                class="h-9 w-9 shrink-0 rounded-lg border-2 border-slate-300 pointer-coarse:h-11 pointer-coarse:w-11 dark:border-slate-600"
                                :style="'background-color: ' + color"
                            ></button>
                        </template>
                        <button aria-label="{{ __('Custom QR color') }}"
                            type="button"
                            @click="showColorPicker = !showColorPicker"
                            class="icon-btn border border-slate-300 dark:border-slate-600"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </button>
                    </div>
                    <div x-show="showColorPicker" class="flex items-center gap-2">
                        <input 
                            type="color" 
                            x-model="qrSettings.fgColor"
                            class="h-9 w-14 shrink-0 cursor-pointer rounded-lg border border-slate-300 dark:border-slate-600"
                        >
                        <input 
                            type="text" 
                            x-model="qrSettings.fgColor"
                            placeholder="#000000"
                            class="input min-w-0 flex-1 font-mono"
                        >
                    </div>
                </div>
                <div>
                    <label class="label">
                        {{ __('Background Color') }}
                    </label>
                    <div class="flex items-center gap-2">
                        <input 
                            type="color" 
                            x-model="qrSettings.bgColor"
                            class="h-9 w-14 shrink-0 cursor-pointer rounded-lg border border-slate-300 dark:border-slate-600"
                        >
                        <input 
                            type="text" 
                            x-model="qrSettings.bgColor"
                            placeholder="#ffffff"
                            class="input min-w-0 flex-1 font-mono"
                        >
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="label mb-0!">
                            {{ __('Add Logo') }} <span class="text-xs text-slate-500 dark:text-slate-400">({{ __('optional') }})</span>
                        </label>
                        <button 
                            type="button"
                            @click="qrSettings.showLogo = !qrSettings.showLogo"
                            class="btn-outline"
                            :class="qrSettings.showLogo 
                                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' 
                                : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-slate-700'"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-show="qrSettings.showLogo">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-show="!qrSettings.showLogo">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span class="text-sm font-medium" x-text="qrSettings.showLogo ? @js(__('Logo Enabled')) : @js(__('Add Logo'))"></span>
                        </button>
                    </div>
                    <div x-show="qrSettings.showLogo" x-transition class="mt-2">
                        <label class="label">
                            {{ __('Logo URL') }}
                        </label>
                        <input 
                            type="url" 
                            x-model="qrSettings.logo"
                            placeholder="https://example.com/logo.png"
                            class="input"
                        >
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('The logo is placed in the center of the QR code. Use a square image with a transparent background for best results.') }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="form-actions shrink-0 border-t border-slate-200 pt-4 dark:border-slate-700 px-5 pb-5">
                <button
                    type="button"
                    @click="resetQrSettings()"
                    class="btn-outline"
                >
                    {{ __('Reset') }}
                </button>
                <button 
                    type="button"
                    @click="closeSheet(); generateQrPreview();"
                    class="btn-primary"
                >
                    {{ __('Done') }}
                </button>
            </div>
        </div>
    </div>
    <div
        x-show="showFolderModal"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="showFolderModal = false"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Create new folder') }}"
            class="relative z-210 w-full rounded-xl bg-white dark:bg-slate-800 shadow-2xl p-5"
            style="max-width: 400px;"
        >
            <h4 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Create new folder') }}</h4>
            <form @submit.prevent="createFolder()">
                <input 
                    type="text" 
                    x-model="newFolderName"
                    placeholder="{{ __('Folder name') }}"
                    required
                    class="input mb-4"
                >
                <div class="form-actions">
                    <button 
                        type="button"
                        @click="showFolderModal = false"
                        class="btn-outline"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        type="submit"
                        class="btn-primary"
                    >
                        {{ __('Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div
        x-show="showTagModal"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="showTagModal = false"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Create new tag') }}"
            class="relative z-210 w-full rounded-xl bg-white dark:bg-slate-800 shadow-2xl p-5"
            style="max-width: 400px;"
        >
            <h4 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Create new tag') }}</h4>
            <form @submit.prevent="createTag()">
                <input 
                    type="text" 
                    x-model="newTagName"
                    placeholder="{{ __('Tag name') }}"
                    required
                    class="input mb-4"
                >
                <div class="form-actions">
                    <button 
                        type="button"
                        @click="showTagModal = false"
                        class="btn-outline"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        type="submit"
                        class="btn-primary"
                    >
                        {{ __('Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script nonce="{{ Vite::cspNonce() }}">
function linkCreationModal() {
    return {
        panel: false,
        // The option sheet that is open (utm|qr|og|schedule|protect|app|routing|wa), and the chip to give the focus back to.
        sheet: null,
        sheetTrigger: null,
        showFolderModal: false,
        showTagModal: false,
        createdLink: null,
        created: 0, // links made since the modal opened: the page behind it reloads when it closes
        isLoading: false,
        ogLoading: false,
        error: '',
        previewPlatform: 'default',
        qrPreviewUrl: null,
        qrPreviewLoading: false,
        qrEditorLoading: false,
        newFolderName: '',
        newTagName: '',
        ogFetchTimeout: null,
        slugGenerateTimeout: null,
        slugCheck: window.hopSlugChecker('{{ route('links.slug-check') }}'),
        showColorPicker: false,
        qrColorPresets: ['#000000', '#1e40af', '#dc2626', '#059669', '#7c3aed', '#db2777'],
        folderSuccess: false,
        tagSuccess: false,
        activeDropdown: null,
        tagSearch: '',
        folderSearch: '',
        folders: @json($folders),
        tags: @json($tags),
        qrSettings: {
            fgColor: '#000000',
            bgColor: '#ffffff',
            logo: '',
            showLogo: false,
            generateQr: false
        },
        formData: {
            content_warning: 'none',
            warning_reason: '',
            target_url: '',
            slug: '',
            folder_id: '',
            tags: [],
            comment: '',
            status: 'active',
            expires_at: '',
            starts_at: '',
            max_clicks: '',
            fallback_url: '',
            routing_rules: [],
            app_links: { mode: 'off', ios: '', android: '', ios_store: '', android_store: '' },
            wa_mode: '',
            wa_numbers: [],
            store_mode: false,
            store_links: { play: '', appstore: '', macstore: '', msstore: '' },
            ab_variants: [],
            password: '',
            redirect_type: '302'
        },
        ogData: {
            title: '',
            description: '',
            image: ''
        },
        init() {
            // Set default folder (Default folder)
            const defaultFolder = this.folders.find(f => f.is_default);
            if (defaultFolder) {
                this.formData.folder_id = defaultFolder.id;
            }
            // Expose function immediately; a preset ('qr', 'deeplink') opens the matching option sheet.
            window.openLinkPanel = (preset) => {
                this.panel = true;
                this.resetForm();
                this.applyPreset(preset);
            };
            // Pages without this modal send "Create" here with ?create=1 (or ?create=qr / ?create=deeplink).
            const params = new URLSearchParams(window.location.search);
            const create = params.get('create');
            if (create) {
                // Read once: drop it from the address so a reload after creating a link does not reopen the dialog.
                params.delete('create');
                const rest = params.toString();
                try { window.history.replaceState(window.history.state, '', window.location.pathname + (rest ? '?' + rest : '') + window.location.hash); } catch (e) {}
                this.$nextTick(() => window.openLinkPanel(create === '1' ? undefined : create));
            }
        },
        applyPreset(preset) {
            if (preset === 'qr') {
                this.qrSettings.generateQr = true;
                this.openSheet('qr');
            } else if (preset === 'deeplink') {
                this.formData.app_links.mode = 'auto';
                this.openSheet('app');
            }
        },
        openSheet(name, trigger) {
            this.sheet = name;
            this.sheetTrigger = trigger || null;
            if (name === 'utm') {
                // Let the campaign tag builder re-read the tags already on the address.
                window.dispatchEvent(new CustomEvent('open-utm'));
            }
            this.$nextTick(() => {
                document.querySelector(`[data-sheet="${name}"] input, [data-sheet="${name}"] select, [data-sheet="${name}"] textarea, [data-sheet="${name}"] button`)?.focus();
            });
        },
        closeSheet() {
            this.sheet = null;
            const trigger = this.sheetTrigger;
            this.sheetTrigger = null;
            if (trigger) {
                this.$nextTick(() => trigger.focus());
            }
        },
        closePanel() {
            this.panel = false;
            // The list and the "N short links" count behind the modal are server-rendered: show the new link(s).
            if (this.created > 0) {
                window.location.reload();
                return;
            }
            setTimeout(() => {
                this.resetForm();
            }, 300);
        },
        // A message in the family dialog (hop-ui) when the page has it, in the browser's own otherwise.
        say(title, message) {
            return window.hopDialog.alert({ title, message });
        },
        // JSON POST with the page's CSRF token. A 419 (the session token changed, e.g. another tab signed in
        // through the hub) fetches a fresh token and sends the same request once more.
        async postJson(url, payload, retried = false) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            if (response.status === 419 && !retried && typeof window.hopFreshCsrf === 'function' && await window.hopFreshCsrf()) {
                return this.postJson(url, payload, true);
            }
            // Read the body once: some errors (quota, CSRF) are plain text, not JSON.
            const text = await response.text();
            let data = {};
            try { data = JSON.parse(text); } catch (e) { data = { message: text }; }
            return { response, data };
        },
        resetForm() {
            this.createdLink = null;
            this.sheet = null;
            this.sheetTrigger = null;
            // Set default folder again on reset
            const defaultFolder = this.folders.find(f => f.is_default);
            this.formData = {
                content_warning: 'none',
                warning_reason: '',
                target_url: '',
                slug: '',
                folder_id: defaultFolder ? defaultFolder.id : '',
                tags: [],
                comment: '',
                status: 'active',
                expires_at: '',
                starts_at: '',
                max_clicks: '',
                fallback_url: '',
                routing_rules: [],
                app_links: { mode: 'off', ios: '', android: '', ios_store: '', android_store: '' },
                wa_mode: '',
                wa_numbers: [],
                store_mode: false,
                store_links: { play: '', appstore: '', macstore: '', msstore: '' },
                ab_variants: [],
                password: '',
                redirect_type: '302'
            };
            this.ogData = {
                title: '',
                description: '',
                image: ''
            };
            this.qrPreviewUrl = null;
            this.resetQrSettings();
            this.slugCheck.run('');
        },
        resetSchedule() {
            this.formData.starts_at = '';
            this.formData.expires_at = '';
            this.formData.max_clicks = '';
            this.formData.fallback_url = '';
        },
        resetProtection() {
            this.formData.password = '';
            this.formData.comment = '';
            this.formData.content_warning = 'none';
            this.formData.warning_reason = '';
            this.formData.redirect_type = '302';
        },
        tagsLabel() {
            const n = this.formData.tags.length;
            if (n === 0) return @js(__('No tags selected'));
            return @js(__(':count tag(s) selected')).replace(':count', n);
        },
        // Short summary on an option chip: empty means the option was not touched.
        chipSummary(name) {
            const f = this.formData;
            const on = @js(__('On'));
            switch (name) {
                case 'utm':
                    return /[?&]utm_/.test(f.target_url || '') ? @js(__('Added')) : '';
                case 'qr':
                    return this.qrSettings.generateQr ? on : '';
                case 'og':
                    return (this.ogData.title || this.ogData.description || this.ogData.image) ? @js(__('Custom')) : '';
                case 'schedule': {
                    const n = [f.starts_at, f.expires_at, f.max_clicks, f.fallback_url].filter(v => v !== '' && v !== null && v !== undefined).length;
                    return n ? @js(__(':count set')).replace(':count', n) : '';
                }
                case 'protect': {
                    const n = [f.password, f.comment, f.content_warning !== 'none' ? 1 : '', f.redirect_type !== '302' ? 1 : ''].filter(v => v).length;
                    return n ? @js(__(':count set')).replace(':count', n) : '';
                }
                case 'app':
                    if (f.app_links.mode === 'auto') return @js(__('Auto'));
                    if (f.app_links.mode === 'custom') return @js(__('Custom'));
                    if (f.store_mode) return @js(__('Store links'));
                    return '';
                case 'routing': {
                    const parts = [];
                    if (f.routing_rules.length) parts.push(@js(__(':count rules')).replace(':count', f.routing_rules.length));
                    if (f.ab_variants.length) parts.push('A/B');
                    return parts.join(' · ');
                }
                case 'wa':
                    return f.wa_mode ? on : '';
            }
            return '';
        },
        normalizedUrl() {
            const url = (this.formData.target_url || '').trim();
            return url && !/^https?:\/\//i.test(url) ? 'https://' + url : url;
        },
        async submitForm() {
            if (this.isLoading) return;
            this.error = '';
            if (this.formData.wa_mode) {
                // A rotator has no destination of its own: the server takes the first number's address.
                this.formData.target_url = '';
            } else if (!this.formData.target_url.trim()) {
                this.error = @js(__('Enter a destination URL.'));
                return;
            }
            this.formData.target_url = this.normalizedUrl();
            this.isLoading = true;
            const payload = {
                content_warning: this.formData.content_warning,
                warning_reason: this.formData.warning_reason || null,
                target_url: this.formData.target_url,
                slug: this.formData.slug || '',
                status: this.formData.status || 'active',
                folder_id: this.formData.folder_id || null,
                tags: this.formData.tags || [],
                expires_at: this.formData.expires_at || null,
                starts_at: this.formData.starts_at || null,
                max_clicks: this.formData.max_clicks ? Number(this.formData.max_clicks) : null,
                fallback_url: this.formData.fallback_url || null,
                routing_rules: this.formData.routing_rules,
                app_links: this.formData.app_links,
                store_mode: !!this.formData.store_mode && !this.formData.wa_mode,
                store_links: this.formData.store_links,
                wa_mode: this.formData.wa_mode || null,
                wa_numbers: this.formData.wa_mode ? this.formData.wa_numbers : [],
                ab_variants: this.formData.ab_variants,
                password: this.formData.password || null,
                redirect_type: this.formData.redirect_type || '302',
                comment: this.formData.comment || null,
                custom_title: this.ogData.title || null,
                custom_description: this.ogData.description || null,
                custom_image_url: this.ogData.image || null,
                qr_fg_color: this.qrSettings.generateQr ? (this.qrSettings.fgColor || '#000000') : null,
                qr_bg_color: this.qrSettings.generateQr ? (this.qrSettings.bgColor || '#ffffff') : null,
                qr_logo_url: this.qrSettings.generateQr && this.qrSettings.showLogo && this.qrSettings.logo ? this.qrSettings.logo : null,
                generate_qr: this.qrSettings.generateQr
            };
            try {
                const { response, data } = await this.postJson('{{ route('links.store') }}', payload);
                if (response.ok && data.success) {
                    this.createdLink = data.link;
                    this.created++;
                    return;
                }
                const fieldErrors = data.errors ? Object.values(data.errors).flat() : [];
                this.error = (response.status === 419 ? @js(__('Your session expired. Reload the page and try again.')) : '')
                    || fieldErrors[0] || data.error || data.message || @js(__('Could not create link (:status).')).replace(':status', response.status);
            } catch (e) {
                this.error = @js(__('Network error. Check your connection and try again.'));
            } finally {
                this.isLoading = false;
            }
        },
        debouncedFetchOg() {
            clearTimeout(this.ogFetchTimeout);
            this.ogFetchTimeout = setTimeout(() => this.fetchOgData(), 1000);
        },
        debouncedGenerateSlug() {
            clearTimeout(this.slugGenerateTimeout);
            this.slugGenerateTimeout = setTimeout(() => {
                // the visitor may have typed their own slug while the timer ran
                if (this.formData.slug) return;
                this.generateRandomSlug();
            }, 1000);
        },
        detectPlatform(url) {
            if (!url) return 'default';
            const lowerUrl = url.toLowerCase();
            if (lowerUrl.includes('instagram.com')) return 'instagram';
            if (lowerUrl.includes('facebook.com') || lowerUrl.includes('fb.com')) return 'facebook';
            if (lowerUrl.includes('twitter.com') || lowerUrl.includes('x.com')) return 'twitter';
            if (lowerUrl.includes('linkedin.com')) return 'linkedin';
            if (lowerUrl.includes('tiktok.com')) return 'tiktok';
            return 'default';
        },
        getPreviewSize(platform) {
            const sizes = {
                'instagram': { width: 1080, height: 1080, name: @js(__('Instagram Square')) },
                'facebook': { width: 1200, height: 630, name: @js(__('Facebook Share')) },
                'twitter': { width: 1200, height: 675, name: @js(__('Twitter Card')) },
                'linkedin': { width: 1200, height: 627, name: @js(__('LinkedIn Share')) },
                'tiktok': { width: 1080, height: 1920, name: @js(__('TikTok Vertical')) },
                'default': { width: 1200, height: 630, name: @js(__('Default OG')) }
            };
            const size = sizes[platform] || sizes.default;
            return { width: size.width, height: size.height, label: `${size.name} (${size.width}×${size.height})` };
        },
        async fetchOgData() {
            if (!this.formData.target_url || this.formData.target_url.length < 10) return;
            this.ogLoading = true;
            try {
                const response = await fetch('{{ route('links.fetch-og') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ url: this.normalizedUrl() })
                });
                const result = await response.json();
                if (result.success && result.data) {
                    this.ogData.title = result.data.title || '';
                    this.ogData.description = result.data.description || '';
                    this.ogData.image = result.data.image || '';
                }
            } catch (error) {
            } finally {
                this.ogLoading = false;
            }
        },
        generateRandomSlug() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let slug = '';
            for (let i = 0; i < 6; i++) {
                slug += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            this.formData.slug = slug;
            this.generateQrPreview();
            this.slugCheck.run(slug);
        },
        generateQrPreview() {
            if (this.formData.slug) {
                const shortUrl = `{{ public_url('l') }}/${this.formData.slug}?src=qr`;
                const fgColor = this.qrSettings.fgColor.replace('#', '');
                const bgColor = this.qrSettings.bgColor.replace('#', '');
                const url = `{{ route('api.qr.generate') }}?size=200&data=${encodeURIComponent(shortUrl)}&color=${fgColor}&bgcolor=${bgColor}`;
                if (url !== this.qrPreviewUrl) {
                    this.qrPreviewLoading = true;
                    this.qrPreviewUrl = url;
                }
            } else {
                this.qrPreviewUrl = null;
                this.qrPreviewLoading = false;
            }
        },
        resetOgData() {
            this.fetchOgData();
        },
        async createFolder() {
            if (!this.newFolderName) return;
            try {
                const { response, data: result } = await this.postJson('{{ route('folders.store') }}', { name: this.newFolderName });
                if (!response.ok) {
                    const errorMsg = result.message || result.error || `Server error: ${response.status}`;
                    console.error('Folder creation failed:', result);
                    this.say(@js(__('Could not create the folder')), errorMsg);
                    return;
                }
                if (result.success && result.folder) {
                    // Ensure folders array exists
                    if (!Array.isArray(this.folders)) {
                        this.folders = [];
                    }
                    // Add new folder to list dynamically
                    this.folders.push(result.folder);
                    // Set as selected
                    this.formData.folder_id = result.folder.id;
                    // Close modal
                    this.showFolderModal = false;
                    this.newFolderName = '';
                    // Show success message
                    this.folderSuccess = true;
                    setTimeout(() => { this.folderSuccess = false; }, 3000);
                } else {
                    this.say(@js(__('Could not create the folder')), result.error || result.message || '');
                }
            } catch (error) {
                console.error('Error creating folder:', error);
                this.say(@js(__('Could not create the folder')), @js(__('Network error. Check your connection and try again.')));
            }
        },
        async createTag() {
            if (!this.newTagName) return;
            try {
                const { response, data: result } = await this.postJson('{{ route('tags.store') }}', { name: this.newTagName });
                if (!response.ok) {
                    const errorMsg = result.message || result.error || `Server error: ${response.status}`;
                    console.error('Tag creation failed:', result);
                    this.say(@js(__('Could not create the tag')), errorMsg);
                    return;
                }
                if (result.success && result.tag) {
                    // Ensure tags array exists
                    if (!Array.isArray(this.tags)) {
                        this.tags = [];
                    }
                    // Add new tag to list dynamically
                    this.tags.push(result.tag);
                    // A tag made from here is meant for this link: tick it.
                    if (!this.formData.tags.includes(result.tag.id)) {
                        this.formData.tags.push(result.tag.id);
                    }
                    // Close modal
                    this.showTagModal = false;
                    this.newTagName = '';
                    // Show success message
                    this.tagSuccess = true;
                    setTimeout(() => { this.tagSuccess = false; }, 3000);
                } else {
                    this.say(@js(__('Could not create the tag')), result.error || result.message || '');
                }
            } catch (error) {
                console.error('Error creating tag:', error);
                this.say(@js(__('Could not create the tag')), @js(__('Network error. Check your connection and try again.')));
            }
        },
        generateQrUrl() {
            if (!this.formData.slug) return '';
            const shortUrl = `{{ public_url('l') }}/${this.formData.slug}?src=qr`;
            const fgColor = this.qrSettings.fgColor.replace('#', '');
            const bgColor = this.qrSettings.bgColor.replace('#', '');
            return `{{ route('api.qr.generate') }}?size=400&data=${encodeURIComponent(shortUrl)}&color=${fgColor}&bgcolor=${bgColor}`;
        },
        resetQrSettings() {
            this.qrSettings = {
                fgColor: '#000000',
                bgColor: '#ffffff',
                logo: '',
                showLogo: false,
                generateQr: false
            };
            this.showColorPicker = false;
        }
    };
}
</script>
