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
    {{-- Escape closes the innermost layer only: a sub-dialog, then an open folder/tag list, then the modal itself. --}}
    @keydown.escape.window="if (showOgEditor) { showOgEditor = false; } else if (showQrEditor) { showQrEditor = false; } else if (showFolderModal) { showFolderModal = false; } else if (showTagModal) { showTagModal = false; } else if (activeDropdown) { activeDropdown = null; tagSearch = ''; folderSearch = ''; } else if (panel) { closePanel(); }"
>
    <div @click="if (!showOgEditor && !showFolderModal && !showTagModal && !showQrEditor) { closePanel(); }" class="absolute inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
    <div
        x-show="!showOgEditor && !showFolderModal && !showTagModal && !showQrEditor"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative z-10 w-full max-w-5xl max-h-[90vh] rounded-2xl glass-strong no-skin flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-label="Create short link"
    >
        <div x-show="createdLink" class="flex flex-col h-full">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4 sm:px-6 dark:border-slate-800">
                <div>
                    <h3 class="text-lg font-semibold text-emerald-600 dark:text-emerald-400">Link created</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Your short link is ready to use</p>
                </div>
                <button type="button" aria-label="Close" @click="closePanel()" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                <div class="max-w-2xl mx-auto space-y-4">
                    <div class="rounded-xl border-2 border-emerald-300 bg-emerald-50 p-4 sm:p-5 dark:border-emerald-800 dark:bg-emerald-900/20">
                        <label for="flinkcr-1" class="block text-sm font-medium text-emerald-800 dark:text-emerald-300 mb-2">Your short URL</label>
                        {{-- One column on a phone: the input keeps its full width and the Copy button stays on screen. --}}
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                            <input id="flinkcr-1"
                                type="text"
                                :value="createdLink?.short_url"
                                readonly
                                class="w-full min-w-0 flex-1 rounded-lg border-0 bg-white px-4 py-3 font-mono text-base font-semibold text-emerald-700 sm:text-lg dark:bg-slate-800 dark:text-emerald-400"
                            >
                            <button
                                type="button"
                                data-copied="{{ __('Copied') }}"
                                data-failed="{{ __('Copy failed') }}"
                                @click="hopCopy(createdLink?.short_url || '', $el)"
                                class="shrink-0 rounded-lg bg-emerald-600 px-6 py-3 font-semibold text-white hover:bg-emerald-500 transition-colors whitespace-nowrap"
                            >
                                📋 {{ __('Copy') }}
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" x-show="createdLink?.qr_code_url">
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">QR Code</h4>
                        <div class="flex flex-col items-start gap-4 sm:flex-row">
                            <div class="shrink-0">
                                <img :src="createdLink?.qr_code_url" alt="QR Code" class="w-[150px] h-[150px] object-contain rounded-lg border border-slate-200 dark:border-slate-700 bg-white">
                            </div>
                            <div class="min-w-0 flex-1 space-y-4">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Download your custom QR code as PNG, JPG, or SVG</p>
                                <div class="flex flex-wrap gap-2">
                                    <a 
                                        :href="`/links/${createdLink?.id}/qr/png`" 
                                        download 
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        PNG
                                    </a>
                                    <a 
                                        :href="`/links/${createdLink?.id}/qr/jpg`" 
                                        download 
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-500 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        JPG
                                    </a>
                                    <a 
                                        :href="`/links/${createdLink?.id}/qr/svg`" 
                                        download 
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        SVG
                                    </a>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">SVG stays sharp when resized for printing</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:gap-3">
                        <button
                            type="button"
                            @click="resetForm()"
                            class="flex-1 rounded-lg border border-slate-300 px-4 py-3 font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Create another link
                        </button>
                        <button
                            type="button"
                            @click="closePanel()"
                            class="flex-1 rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-500"
                        >
                            View dashboard
                        </button>
                    </div>
                </div>
            </div>
        </div>
        {{-- The form wraps both columns, so on a phone the folder, QR and preview come before the Create button
             (one column below lg) instead of after it. --}}
        <form x-show="!createdLink" @submit.prevent="submitForm()" class="flex min-h-0 flex-col overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4 sm:px-6 dark:border-slate-800">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Create new link</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Shorten and customize your URL</p>
                </div>
                <button type="button" aria-label="Close" @click="closePanel()" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-white">
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
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Destination URL
                                    <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    id="modal-target-url"
                                    type="url" 
                                    x-model="formData.target_url"
                                    @input="error = ''; debouncedFetchOg(); if(!formData.slug) { debouncedGenerateSlug(); }"
                                    @paste="$nextTick(() => { clearTimeout(ogFetchTimeout); fetchOgData(); })"
                                    placeholder="https://example.com"
                                    required
                                    class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                                >
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Enter the long URL you want to shorten</p>
                                {{-- Preview of the page you pasted (the same lookup as the social card) --}}
                                <div x-show="ogLoading || ogData.title" x-cloak class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800/60" aria-live="polite">
                                    <p x-show="ogLoading && !ogData.title" class="text-xs text-slate-500 dark:text-slate-400">{{ __('Looking up the page…') }}</p>
                                    <p x-show="ogData.title" class="truncate text-sm font-medium text-slate-800 dark:text-slate-100" x-text="ogData.title"></p>
                                    <p x-show="ogData.description" class="line-clamp-2 text-xs text-slate-500 dark:text-slate-400" x-text="ogData.description"></p>
                                </div>
                            </div>
                            <x-utm-builder target="modal-target-url" />
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Short Link
                                </label>
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        x-model="formData.slug"
                                        @input="generateQrPreview()"
                                        @input.debounce.350ms="slugCheck.run(formData.slug)"
                                        placeholder="custom-slug"
                                        pattern="[a-zA-Z0-9_\-]+"
                                        class="min-w-0 flex-1 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                                    >
                                    <button 
                                        type="button"
                                        @click="generateRandomSlug()"
                                        class="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm font-medium"
                                    >
                                        🎲 Random
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave empty to generate a slug automatically</p>
                                <p x-show="slugCheck.state === 'ok' || slugCheck.state === 'bad'" x-cloak x-text="slugCheck.message" role="status" aria-live="polite"
                                   class="mt-1 text-xs" :class="slugCheck.state === 'ok' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"></p>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                            Tags
                                        </label>
                                        <span x-show="tagSuccess" x-transition class="text-xs text-emerald-600 dark:text-emerald-400">Tag created.</span>
                                    </div>
                                    <button 
                                        type="button"
                                        @click="showTagModal = true"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        + New tag
                                    </button>
                                </div>
                                {{-- Escape here closes only this list (see the window handler above). --}}
                                <div class="relative" @click.outside="if (activeDropdown === 'tags') { activeDropdown = null; tagSearch = ''; }" @keydown.escape.stop="activeDropdown = null; tagSearch = ''">
                                    <button
                                        type="button"
                                        @click="activeDropdown = activeDropdown === 'tags' ? null : 'tags'; tagSearch = ''"
                                        class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-left flex items-center justify-between border"
                                    >
                                        <span class="text-sm" x-text="formData.tags.length === 0 ? 'No tags selected' : formData.tags.length + ' tag(s) selected'"></span>
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
                                                placeholder="Search tags..."
                                                aria-label="{{ __('Search tags') }}"
                                                class="w-full px-3 py-1.5 text-sm rounded-sm border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                                                @click.stop
                                                @keydown.enter.prevent
                                            >
                                        </div>
                                        <div class="overflow-y-auto" style="max-height: 160px;">
                                            <template x-if="tags.length === 0">
                                                <p class="text-xs text-slate-500 dark:text-slate-400 text-center py-4">No tags yet</p>
                                            </template>
                                            <template x-for="tag in tags.filter(t => !tagSearch || t.name.toLowerCase().includes(tagSearch.toLowerCase()))" :key="tag.id">
                                                <label class="flex items-center justify-between px-3 py-2.5 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer border-b border-slate-100 dark:border-slate-700 last:border-b-0">
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
                                                        class="rounded-sm border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600"
                                                    >
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Comments
                                </label>
                                <textarea 
                                    x-model="formData.comment"
                                    rows="3"
                                    placeholder="Add internal notes..."
                                    class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                                ></textarea>
                            </div>
                            {{-- After the address and the slug: most links need no warning, so it does not lead the form. --}}
                            <x-content-warning-fields :model="true" prefix="modal-warning" />
                            <div>
                                <button 
                                    type="button"
                                    @click="showAdvanced = !showAdvanced"
                                    class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                                >
                                    <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-90': showAdvanced }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    Advanced options
                                </button>
                            </div>
                            <div x-show="showAdvanced" x-collapse class="space-y-4 pt-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        {{ __('Expires at') }} <span class="font-normal text-slate-400">(UTC)</span>
                                    </label>
                                    <input
                                        type="datetime-local"
                                        x-model="formData.expires_at"
                                        class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Starts at') }} <span class="font-normal text-slate-400">(UTC)</span></label>
                                    <input type="datetime-local" x-model="formData.starts_at" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500">
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Until then the link says it is not live yet.') }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Stop after this many clicks') }}</label>
                                    <input type="number" min="1" max="10000000" inputmode="numeric" x-model="formData.max_clicks" placeholder="{{ __('Leave empty for no limit') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500">
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Visits by crawlers and link-preview bots are not counted.') }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('When the link has ended, send visitors to') }}</label>
                                    <input type="url" x-model="formData.fallback_url" placeholder="https://" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500">
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Used after the expiry date or when the clicks run out. Empty shows a notice.') }}</p>
                                </div>
                                <x-link-routing-fields :model="true" />
                                <x-link-app-fields :model="true" />
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Password protection
                                    </label>
                                    <input 
                                        type="password" 
                                        x-model="formData.password"
                                        placeholder="Leave empty for no password"
                                        class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Redirect type
                                    </label>
                                    <select 
                                        x-model="formData.redirect_type"
                                        class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                                    >
                                        <option value="302">302 (Temporary)</option>
                                        <option value="301">301 (Permanent)</option>
                                        <option value="307">307 (Temporary, Keep Method)</option>
                                        <option value="308">308 (Permanent, Keep Method)</option>
                                    </select>
                                    <p x-show="formData.redirect_type === '301' || formData.redirect_type === '308'" x-cloak class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ __('Browsers remember a permanent redirect. A warning, password, rule, limit or new destination added later will not reach visitors who already opened the link.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="min-w-0 bg-white dark:bg-slate-800">
                        <div class="space-y-6 p-4 sm:p-6">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                            Folder
                                        </label>
                                        <span x-show="folderSuccess" x-transition class="text-xs text-emerald-600 dark:text-emerald-400">Folder created.</span>
                                    </div>
                                    <button 
                                        type="button"
                                        @click="showFolderModal = true"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        + New folder
                                    </button>
                                </div>
                                <div class="relative" @click.outside="if (activeDropdown === 'folders') { activeDropdown = null; folderSearch = ''; }" @keydown.escape.stop="activeDropdown = null; folderSearch = ''">
                                    <button
                                        type="button"
                                        @click="activeDropdown = activeDropdown === 'folders' ? null : 'folders'; folderSearch = ''"
                                        class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-left flex items-center justify-between border"
                                    >
                                        <span class="text-sm" x-text="folders.find(f => f.id == formData.folder_id)?.name || 'Select folder'"></span>
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
                                                placeholder="Search folders..."
                                                aria-label="{{ __('Search folders') }}"
                                                class="w-full px-3 py-1.5 text-sm rounded-sm border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                                                @click.stop
                                                @keydown.enter.prevent
                                            >
                                        </div>
                                        <div class="overflow-y-auto" style="max-height: 160px;">
                                            <template x-for="folder in folders.filter(f => !folderSearch || f.name.toLowerCase().includes(folderSearch.toLowerCase()))" :key="folder.id">
                                                <button
                                                    type="button"
                                                    @click="formData.folder_id = folder.id; activeDropdown = null; folderSearch = ''"
                                                    class="w-full text-left px-3 py-2.5 hover:bg-slate-100 dark:hover:bg-slate-700 border-b border-slate-100 dark:border-slate-700 last:border-b-0"
                                                    :class="formData.folder_id == folder.id ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-slate-700 dark:text-slate-300'"
                                                >
                                                    <span class="text-sm" x-text="folder.name"></span>
                                                </button>
                                            </template>
                                            <template x-if="folders.filter(f => !folderSearch || f.name.toLowerCase().includes(folderSearch.toLowerCase())).length === 0">
                                                <p class="text-xs text-slate-500 dark:text-slate-400 text-center py-4">No folders found</p>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        QR code preview
                                    </label>
                                </div>
                                <div class="space-y-3">
                                    <button 
                                        type="button"
                                        @click="qrSettings.generateQr = !qrSettings.generateQr; if(qrSettings.generateQr) generateQrPreview();"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border transition-all duration-200"
                                        :class="qrSettings.generateQr 
                                            ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400' 
                                            : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:border-emerald-400 hover:bg-emerald-50 dark:hover:bg-slate-700'"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-show="qrSettings.generateQr">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-show="!qrSettings.generateQr">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span class="text-sm font-medium" x-text="qrSettings.generateQr ? 'QR Enabled' : 'Generate QR'"></span>
                                    </button>
                                    <button 
                                        type="button"
                                        @click.prevent="showQrEditor = true"
                                        x-show="qrSettings.generateQr"
                                        class="w-full flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors border border-blue-200 dark:border-blue-800"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                        </svg>
                                        Customize QR
                                    </button>
                                </div>
                                <div class="rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-600 p-4 text-center bg-white dark:bg-slate-800" :style="'background-color: ' + qrSettings.bgColor" x-show="qrSettings.generateQr">
                                    <div x-show="formData.slug" class="space-y-2">
                                        <div class="relative inline-block">
                                            {{-- The code is drawn on the server (about a second): say so instead of showing an empty box. --}}
                                            <img :src="qrPreviewUrl" alt="QR Preview" loading="eager" @load="qrPreviewLoading = false" x-on:error="qrPreviewLoading = false" class="w-32 h-32 mx-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                            <div x-show="qrPreviewLoading" x-cloak class="absolute inset-0 flex items-center justify-center rounded-lg bg-slate-100/90 text-xs text-slate-500 dark:bg-slate-800/90 dark:text-slate-300" role="status">{{ __('Generating…') }}</div>
                                            <div x-show="qrSettings.showLogo && qrSettings.logo" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                                <div class="w-11 h-11 rounded-lg bg-white dark:bg-slate-900 p-1 shadow-lg border border-slate-200 dark:border-slate-700">
                                                    <img :src="qrSettings.logo" alt="Logo" class="w-full h-full object-contain rounded-sm">
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">QR will be generated after link creation</p>
                                    </div>
                                    <div x-show="!formData.slug" class="py-8">
                                        <svg class="w-16 h-16 mx-auto text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                        </svg>
                                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Enter a slug to preview the QR code</p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Link Preview
                                    </label>
                                    <button 
                                        type="button"
                                        @click.prevent="showOgEditor = true"
                                        class="flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
                                    >
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                        Custom Preview
                                    </button>
                                </div>
                                <div class="mb-3 grid grid-cols-2 gap-1 rounded-lg bg-slate-100 p-1 sm:grid-cols-4 dark:bg-slate-700" role="tablist" aria-label="{{ __('Preview platform') }}">
                                    @foreach (['default' => '🌐 Default', 'twitter' => '𝕏 Twitter', 'linkedin' => '💼 LinkedIn', 'facebook' => '📘 Facebook'] as $platform => $platformLabel)
                                        <button
                                            type="button"
                                            role="tab"
                                            @click="previewPlatform = '{{ $platform }}'"
                                            :aria-selected="previewPlatform === '{{ $platform }}' ? 'true' : 'false'"
                                            :class="previewPlatform === '{{ $platform }}' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                                            class="min-w-0 truncate rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                                        >
                                            {{ $platformLabel }}
                                        </button>
                                    @endforeach
                                </div>
                                <div class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-800">
                                    <div x-show="ogLoading && !ogData.title" class="p-8 text-center">
                                        <svg class="animate-spin h-8 w-8 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Fetching preview…</p>
                                    </div>
                                    <div x-show="!ogLoading || ogData.title">
                                        <div class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                                            <p class="text-xs text-slate-600 dark:text-slate-400" x-text="getPreviewSize(previewPlatform).label"></p>
                                        </div>
                                        {{-- Sized by aspect ratio, never wider than the column (a fixed 400px made the modal scroll sideways on a phone).
                                             An image that fails to load hides itself instead of showing the browser's broken-image icon. --}}
                                        <div x-show="ogData.image" class="mx-auto flex w-full max-w-[400px] items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-900" :style="`aspect-ratio: ${getPreviewSize(previewPlatform).width} / ${getPreviewSize(previewPlatform).height}`">
                                            <img :src="ogData.image" :alt="ogData.title" x-on:error="$el.style.visibility = 'hidden'" @load="$el.style.visibility = ''" class="max-w-full max-h-full object-contain">
                                        </div>
                                        <div class="p-4">
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white line-clamp-2" x-text="ogData.title || 'Link Title'"></p>
                                            <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 line-clamp-3" x-text="ogData.description || 'Link description will appear here when you enter a URL or add custom text'"></p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">hel.ink</p>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-center text-slate-500 dark:text-slate-400">
                                    How your link will appear on <span x-text="previewPlatform === 'default' ? 'social platforms' : previewPlatform.charAt(0).toUpperCase() + previewPlatform.slice(1)"></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-2 border-t border-slate-200 px-4 py-3 sm:px-6 lg:flex-row lg:items-center lg:justify-end dark:border-slate-800">
                <p x-show="error" x-cloak x-text="error" role="alert" class="min-w-0 rounded-xl bg-rose-500/10 px-3 py-2 text-sm text-rose-700 lg:mr-auto dark:text-rose-300"></p>
                <button
                    type="submit"
                    :disabled="isLoading || !formData.target_url"
                    class="btn-primary w-full shrink-0 py-3 lg:w-auto lg:px-8"
                >
                    <span x-show="!isLoading">Create Link</span>
                    <span x-show="isLoading" class="flex items-center justify-center gap-2">
                        <svg class="motion-safe:animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating...
                    </span>
                </button>
            </div>
        </form>
    </div>
    <div
        x-show="showOgEditor"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="showOgEditor = false"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            class="relative z-210 w-full max-w-4xl rounded-xl bg-white dark:bg-slate-800 shadow-2xl max-h-[85vh] overflow-hidden"
        >
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4 bg-white dark:bg-slate-800">
                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">Custom link preview</h4>
                <button type="button" aria-label="Close" @click="showOgEditor = false" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            {{-- One column below sm: two narrow columns hid the Save button behind the preview card on a phone. --}}
            <div class="grid max-h-[calc(85vh-80px)] gap-6 overflow-y-auto p-4 sm:grid-cols-2 sm:p-6">
                <div class="min-w-0 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Image URL
                        </label>
                        <input 
                            type="url" 
                            x-model="ogData.image"
                            placeholder="https://example.com/image.jpg"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Title <span class="text-slate-500 font-normal text-xs">(<span x-text="ogData.title.length"></span>/120)</span>
                        </label>
                        <input 
                            type="text" 
                            x-model="ogData.title"
                            maxlength="120"
                            placeholder="Enter a custom title"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Description <span class="text-slate-500 font-normal text-xs">(<span x-text="ogData.description.length"></span>/240)</span>
                        </label>
                        <textarea 
                            x-model="ogData.description"
                            maxlength="240"
                            rows="4"
                            placeholder="Enter a custom description"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 text-sm"
                        ></textarea>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button 
                            type="button"
                            @click="resetOgData()"
                            class="flex-1 rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                        >
                            Reset
                        </button>
                        <button 
                            type="button"
                            @click="showOgEditor = false"
                            class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-500 transition-colors"
                        >
                            Save changes
                        </button>
                    </div>
                </div>
                <div class="min-w-0 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Live Preview</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400" x-text="getPreviewSize(previewPlatform).label"></p>
                    </div>
                    <div class="rounded-xl border-2 border-slate-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-900">
                        <div class="mx-auto flex w-full max-w-[450px] items-center justify-center bg-slate-100 dark:bg-slate-900" :style="`aspect-ratio: ${getPreviewSize(previewPlatform).width} / ${getPreviewSize(previewPlatform).height}`">
                            <img
                                x-show="ogData.image"
                                :src="ogData.image"
                                alt="Preview"
                                x-on:error="$el.style.visibility = 'hidden'" @load="$el.style.visibility = ''"
                                class="max-w-full max-h-full object-contain"
                            >
                            <div x-show="!ogData.image" class="text-slate-400 dark:text-slate-600 text-sm">
                                No image
                            </div>
                        </div>
                        <div class="p-4 bg-white dark:bg-slate-800">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white line-clamp-2 mb-2" x-text="ogData.title || 'Link Title'"></p>
                            <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-3" x-text="ogData.description || 'Link description will appear here'"></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">hel.ink</p>
                        </div>
                    </div>
                </div>
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
            class="relative z-210 w-full rounded-xl bg-white dark:bg-slate-800 shadow-2xl p-5"
            style="max-width: 400px;"
        >
            <h4 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Create new folder</h4>
            <form @submit.prevent="createFolder()">
                <input 
                    type="text" 
                    x-model="newFolderName"
                    placeholder="Folder name"
                    required
                    class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 mb-4"
                >
                <div class="flex gap-3">
                    <button 
                        type="button"
                        @click="showFolderModal = false"
                        class="flex-1 rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500"
                    >
                        Create
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
            class="relative z-210 w-full rounded-xl bg-white dark:bg-slate-800 shadow-2xl p-5"
            style="max-width: 400px;"
        >
            <h4 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Create new tag</h4>
            <form @submit.prevent="createTag()">
                <input 
                    type="text" 
                    x-model="newTagName"
                    placeholder="Tag name"
                    required
                    class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 mb-4"
                >
                <div class="flex gap-3">
                    <button 
                        type="button"
                        @click="showTagModal = false"
                        class="flex-1 rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500"
                    >
                        Create
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div
        x-show="showQrEditor"
        x-transition
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @click="showQrEditor = false"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xs"></div>
        <div 
            @click.stop
            class="relative z-210 w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 shadow-2xl overflow-hidden max-h-[85vh]"
        >
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                <div>
                    <h4 class="text-lg font-semibold text-slate-900 dark:text-white">QR code design</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Customize your QR code appearance</p>
                </div>
                <button aria-label="Close" @click="showQrEditor = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-white transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-5 space-y-4 overflow-y-auto max-h-[70vh]">
                <div class="text-center">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Preview</label>
                    <div class="relative inline-block">
                        <div class="w-32 h-32 mx-auto rounded-xl border-2 border-slate-200 dark:border-slate-700 p-2" :style="'background-color: ' + qrSettings.bgColor">
                            <div class="relative w-full h-full flex items-center justify-center" x-effect="generateQrUrl(); qrEditorLoading = !!formData.slug">
                                <img
                                    :src="generateQrUrl()"
                                    alt="QR Preview"
                                    loading="eager"
                                    @load="qrEditorLoading = false" x-on:error="qrEditorLoading = false"
                                    class="w-full h-full object-contain"
                                    x-show="formData.slug"
                                >
                                <div x-show="qrEditorLoading && formData.slug" x-cloak class="absolute inset-0 flex items-center justify-center rounded-lg bg-white/80 text-xs text-slate-500 dark:bg-slate-900/80 dark:text-slate-300" role="status">{{ __('Generating…') }}</div>
                                <div x-show="!formData.slug" class="text-slate-500 dark:text-slate-400 text-sm">
                                    Enter slug first
                                </div>
                                <div x-show="qrSettings.showLogo && qrSettings.logo" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div class="w-10 h-10 rounded-lg bg-white dark:bg-slate-900 p-1 shadow-lg border border-slate-200 dark:border-slate-700">
                                        <img :src="qrSettings.logo" alt="Logo" class="w-full h-full object-contain rounded-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">
                        QR Code Color
                    </label>
                    <div class="flex items-center gap-2 mb-3 flex-wrap">
                        <template x-for="color in qrColorPresets" :key="color">
                            <button :aria-label="'QR color ' + color"
                                type="button"
                                @click="qrSettings.fgColor = color"
                                :class="qrSettings.fgColor === color ? 'ring-2 ring-offset-2 ring-blue-500 dark:ring-offset-slate-800' : ''"
                                class="w-10 h-10 rounded-lg border-2 border-slate-300 dark:border-slate-600 transition-all hover:scale-110"
                                :style="'background-color: ' + color"
                            ></button>
                        </template>
                        <button aria-label="Custom QR color"
                            type="button"
                            @click="showColorPicker = !showColorPicker"
                            class="w-10 h-10 rounded-lg border-2 border-slate-300 dark:border-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
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
                            class="h-10 w-20 rounded-sm border border-slate-300 dark:border-slate-600 cursor-pointer"
                        >
                        <input 
                            type="text" 
                            x-model="qrSettings.fgColor"
                            placeholder="#000000"
                            class="flex-1 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 text-sm"
                        >
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Background Color
                    </label>
                    <div class="flex items-center gap-2">
                        <input 
                            type="color" 
                            x-model="qrSettings.bgColor"
                            class="h-10 w-20 rounded-sm border border-slate-300 dark:border-slate-600 cursor-pointer"
                        >
                        <input 
                            type="text" 
                            x-model="qrSettings.bgColor"
                            placeholder="#ffffff"
                            class="flex-1 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 text-sm"
                        >
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Add Logo <span class="text-xs text-slate-500 dark:text-slate-400">(optional)</span>
                        </label>
                        <button 
                            type="button"
                            @click="qrSettings.showLogo = !qrSettings.showLogo"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border transition-all duration-200"
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
                            <span class="text-sm font-medium" x-text="qrSettings.showLogo ? 'Logo Enabled' : 'Add Logo'"></span>
                        </button>
                    </div>
                    <div x-show="qrSettings.showLogo" x-transition class="mt-2">
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            Logo URL
                        </label>
                        <input 
                            type="url" 
                            x-model="qrSettings.logo"
                            placeholder="https://example.com/logo.png"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 text-sm"
                        >
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            The logo is placed in the center of the QR code. Use a square image with a transparent background for best results.
                        </p>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <button 
                        type="button"
                        @click="resetQrSettings()"
                        class="flex-1 rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                    >
                        Reset
                    </button>
                    <button 
                        type="button"
                        @click="showQrEditor = false; generateQrPreview();"
                        class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-500 transition-colors shadow-lg shadow-blue-500/30"
                    >
                        Apply design
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script nonce="{{ Vite::cspNonce() }}">
function linkCreationModal() {
    return {
        panel: false,
        showAdvanced: false,
        showOgEditor: false,
        showQrEditor: false,
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
            // Expose function immediately
            window.openLinkPanel = () => {
                this.panel = true;
                this.resetForm();
            };
            // Pages without this modal send "New link" here with ?create=1
            if (new URLSearchParams(window.location.search).get('create') === '1') {
                this.$nextTick(() => window.openLinkPanel());
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
            this.showAdvanced = false;
            this.slugCheck.run('');
        },
        normalizedUrl() {
            const url = (this.formData.target_url || '').trim();
            return url && !/^https?:\/\//i.test(url) ? 'https://' + url : url;
        },
        async submitForm() {
            if (this.isLoading) return;
            this.error = '';
            if (!this.formData.target_url.trim()) {
                this.error = 'Enter a destination URL.';
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
                    || fieldErrors[0] || data.error || data.message || `Could not create link (${response.status}).`;
            } catch (e) {
                this.error = 'Network error. Check your connection and try again.';
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
                'instagram': { width: 1080, height: 1080, label: 'Instagram Square (1080×1080)' },
                'facebook': { width: 1200, height: 630, label: 'Facebook Share (1200×630)' },
                'twitter': { width: 1200, height: 675, label: 'Twitter Card (1200×675)' },
                'linkedin': { width: 1200, height: 627, label: 'LinkedIn Share (1200×627)' },
                'tiktok': { width: 1080, height: 1920, label: 'TikTok Vertical (1080×1920)' },
                'default': { width: 1200, height: 630, label: 'Default OG (1200×630)' }
            };
            return sizes[platform] || sizes.default;
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
