@extends('layouts.app')
@section('title', 'Edit Bio - ' . $bioPage->title)
@section('container-width', 'max-w-full')
@push('styles')
<style>
    :root {
        /* Glass surfaces from the app design tokens (resources/css/app.css) */
        --editor-bg: transparent;
        --editor-panel-bg: rgb(var(--glass) / var(--glass-alpha));
        --editor-border: rgb(var(--muted) / 0.2);
        --editor-card-bg: rgb(var(--glass) / 0.55);
        --editor-text: #1e293b;
        --editor-text-muted: #475569;
        --editor-input-bg: #ffffff;
        --editor-input-border: #cbd5e1;
        --editor-hover-bg: #f1f5f9;
    }
    .dark {
        --editor-bg: transparent;
        --editor-panel-bg: rgb(var(--glass) / var(--glass-alpha));
        --editor-border: rgb(255 255 255 / 0.08);
        --editor-card-bg: rgb(var(--glass) / 0.55);
        --editor-text: #f1f5f9;
        --editor-text-muted: #aab6c6;
        --editor-input-bg: #1e293b;
        --editor-input-border: #475569;
        --editor-hover-bg: #334155;
    }
    .editor-container {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 0;
        height: calc(100vh - 64px);
        overflow: hidden;
    }
    .editor-panel {
        height: 100%;
        overflow-y: auto;
        background: var(--editor-panel-bg);
    }
    .preview-panel {
        height: 100%;
        background: transparent;
        display: flex;
        flex-direction: column;
        border-left: 1px solid var(--editor-border);
    }
    .preview-header {
        padding: 12px 16px;
        background: var(--editor-panel-bg);
        backdrop-filter: blur(8px);
        border-bottom: 1px solid var(--editor-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .preview-toggle {
        display: flex;
        gap: 4px;
        background: var(--editor-card-bg);
        padding: 4px;
        border-radius: 8px;
    }
    .preview-toggle button {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        color: var(--editor-text-muted);
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .preview-toggle button.active {
        background: #3b82f6;
        color: white;
    }
    .preview-frame-container {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        overflow: auto;
        background: transparent;
    }
    .mobile-frame {
        width: 375px;
        height: 667px;
        background: #000;
        border-radius: 40px;
        padding: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        position: relative;
    }
    .mobile-frame::before {
        content: '';
        position: absolute;
        top: 12px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 24px;
        background: #1a1a1a;
        border-radius: 12px;
    }
    .mobile-frame-inner {
        width: 100%;
        height: 100%;
        border-radius: 32px;
        overflow: hidden;
        background: #f8fafc;
    }
    .mobile-frame-inner iframe {
        width: 100%;
        height: 100%;
        border: none;
    }
    .desktop-frame {
        display: none;
    }
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    ::-webkit-scrollbar-track {
        background: var(--editor-panel-bg);
        border-radius: 3px;
    }
    ::-webkit-scrollbar-thumb {
        background: var(--editor-border);
        border-radius: 3px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: var(--editor-text-muted);
    }
    * {
        scrollbar-width: thin;
        scrollbar-color: var(--editor-border) var(--editor-panel-bg);
    }
    .editor-tabs {
        display: flex;
        gap: 4px;
        padding: 12px 16px;
        background: var(--editor-card-bg);
        border-bottom: 1px solid var(--editor-border);
        overflow-x: auto;
        position: sticky;
        top: 0;
        z-index: 40;
    }
    .editor-tab {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        color: var(--editor-text-muted);
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    .editor-tab:hover {
        color: var(--editor-text);
        background: var(--editor-hover-bg);
    }
    .editor-tab.active {
        color: #1d4ed8;
        background: rgba(59, 130, 246, 0.1);
    }
    .dark .editor-tab.active { color: #93c5fd; }
    .editor-tab svg {
        width: 16px;
        height: 16px;
    }
    .tab-content {
        padding: 16px;
    }
    .editor-card {
        background: var(--editor-card-bg);
        border: 1px solid var(--editor-border);
        border-radius: 12px;
        margin-bottom: 16px;
        overflow: hidden;
    }
    .editor-card-header {
        padding: 12px 16px;
        border-bottom: 1px solid var(--editor-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .editor-card-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--editor-text);
    }
    .editor-card > :not(.editor-card-header) {
        padding: 16px;
    }
    .form-group { margin-bottom: 12px; }
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 500;
        color: var(--editor-text-muted);
        margin-bottom: 6px;
    }
    .form-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--editor-input-border);
        border-radius: 8px;
        font-size: 14px;
        color: var(--editor-text);
        background: var(--editor-input-bg);
        transition: all 0.2s;
    }
    .form-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
    .form-input::placeholder { color: var(--editor-text-muted); }
    /* Keyboard focus must stay visible on every control (WCAG 2.4.7). */
    .editor-container :is(button, a, [role="tab"], [tabindex]):focus-visible,
    .modal-content :is(button, a, [tabindex]):focus-visible,
    .toggle-switch input:focus-visible + .toggle-slider {
        outline: 2px solid #2563eb;
        outline-offset: 2px;
    }
    .dark .editor-container :is(button, a, [role="tab"], [tabindex]):focus-visible,
    .dark .modal-content :is(button, a, [tabindex]):focus-visible,
    .dark .toggle-switch input:focus-visible + .toggle-slider { outline-color: #93c5fd; }
    @media (prefers-reduced-motion: reduce) {
        .editor-container *, .modal-overlay, .modal-overlay * {
            transition-duration: 0.01ms !important;
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
        }
    }
    .form-textarea { resize: vertical; min-height: 60px; }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }
    .btn-primary {
        background: #2563eb;
        color: white;
    }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
    .btn-secondary {
        background: var(--editor-hover-bg);
        color: var(--editor-text);
    }
    .btn-secondary:hover { background: var(--editor-border); }
    .block-list { }
    .block-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: var(--editor-input-bg);
        border: 1px solid var(--editor-border);
        border-radius: 10px;
        margin-bottom: 8px;
        /* Only colors: animating transform would fight SortableJS's own animation */
        transition: border-color 0.2s, background-color 0.2s, box-shadow 0.2s;
    }
    .block-item:hover { border-color: var(--editor-text-muted); }
    .block-drag-handle {
        cursor: grab;
        color: var(--editor-text-muted);
        padding: 8px 4px;
        touch-action: none;
    }
    .block-drag-handle:active { cursor: grabbing; }
    .block-ghost { opacity: 0.35; border-style: dashed; }
    .block-chosen { box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18); }
    .sortable-fallback { opacity: 0.95 !important; }
    .block-drag-handle:hover { color: var(--editor-text); }
    .block-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .block-content {
        flex: 1;
        min-width: 0;
    }
    .block-title {
        font-size: 14px;
        font-weight: 500;
        color: var(--editor-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .block-subtitle {
        font-size: 12px;
        color: var(--editor-text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .block-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .block-action-btn {
        padding: 6px;
        border-radius: 6px;
        color: var(--editor-text-muted);
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .block-action-btn:hover { color: var(--editor-text); background: var(--editor-hover-bg); }
    .block-action-btn.delete:hover { color: #f87171; background: rgba(248, 113, 113, 0.1); }
    /* Add Block Button */
    .add-block-btn {
        width: 100%;
        padding: 14px;
        border: 2px dashed var(--editor-border);
        border-radius: 10px;
        background: transparent;
        color: var(--editor-text-muted);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .add-block-btn:hover {
        border-color: #3b82f6;
        color: #3b82f6;
        background: rgba(59, 130, 246, 0.05);
    }
    /* Toggle Switch */
    .toggle-switch {
        position: relative;
        width: 44px;
        height: 24px;
        display: inline-block;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background: var(--editor-border);
        border-radius: 24px;
        transition: 0.3s;
    }
    .toggle-slider::before {
        content: '';
        position: absolute;
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background: white;
        border-radius: 50%;
        transition: 0.3s;
    }
    .toggle-switch input:checked + .toggle-slider { background: #3b82f6; }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
    /* Color Picker */
    .color-picker-input {
        width: 36px;
        height: 36px;
        padding: 2px;
        border: 2px solid var(--editor-border);
        border-radius: 8px;
        cursor: pointer;
        background: transparent;
    }
    .color-picker-input::-webkit-color-swatch-wrapper { padding: 0; }
    .color-picker-input::-webkit-color-swatch { border: none; border-radius: 4px; }
    /* Modal */
    .modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
    }
    .modal-content {
        background: var(--editor-card-bg);
        border: 1px solid var(--editor-border);
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        width: 100%;
        max-width: 480px;
        max-height: 90vh;
        overflow: hidden;
        margin: 16px;
    }
    .modal-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--editor-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .modal-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--editor-text);
    }
    .modal-close {
        padding: 6px;
        color: var(--editor-text-muted);
        background: transparent;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    .modal-close:hover { color: var(--editor-text); background: var(--editor-hover-bg); }
    .modal-body {
        padding: 20px;
        overflow-y: auto;
        max-height: calc(90vh - 130px);
    }
    .modal-footer {
        padding: 16px 20px;
        border-top: 1px solid var(--editor-border);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }
    /* Social Dropdown */
    .social-dropdown {
        position: relative;
    }
    .social-dropdown-btn {
        width: 100%;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--editor-input-bg);
        border: 1px solid var(--editor-border);
        border-radius: 8px;
        color: var(--editor-text-muted);
        cursor: pointer;
        transition: all 0.2s;
    }
    .social-dropdown-btn:hover { border-color: var(--editor-text-muted); }
    .social-dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: var(--editor-card-bg);
        border: 1px solid var(--editor-border);
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        z-index: 50;
        max-height: 300px;
        overflow-y: auto;
    }
    .social-dropdown-search {
        padding: 12px;
        border-bottom: 1px solid var(--editor-border);
        position: sticky;
        top: 0;
        background: var(--editor-card-bg);
    }
    .social-dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        cursor: pointer;
        transition: background 0.2s;
    }
    .social-dropdown-item:hover { background: var(--editor-hover-bg); }
    .social-dropdown-icon {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .social-dropdown-icon img {
        width: 18px;
        height: 18px;
        filter: brightness(0) invert(1);
    }
    .social-dropdown-name {
        font-size: 14px;
        color: var(--editor-text);
    }
    .editor-text { color: var(--editor-text); }
    .editor-text-muted { color: var(--editor-text-muted); }
    .editor-icon { color: var(--editor-text-muted); }
    .editor-icon:hover { color: var(--editor-text); }
    .option-btn {
        background: var(--editor-input-bg);
        border: 1px solid var(--editor-border);
        color: var(--editor-text-muted);
    }
    .option-btn:hover {
        background: var(--editor-hover-bg);
    }
    .option-btn.active {
        background: rgba(59, 130, 246, 0.15);
        border-color: #3b82f6;
    }
    @media (max-width: 1024px) {
        .editor-container {
            grid-template-columns: 1fr;
        }
        .preview-panel {
            display: none;
        }
    }
</style>
@endpush
@section('content')
<div x-data="window.bioEditorV2()">
    <!-- Screen reader announcements (reorder, save results) -->
    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="announcement"></div>
    <!-- Fake fields to prevent browser password autofill -->
    <input type="text" name="fake_username" style="display:none !important" tabindex="-1" autocomplete="username">
    <input type="password" name="fake_password" style="display:none !important" tabindex="-1" autocomplete="new-password">
    <div class="editor-container" autocomplete="off">
        <div class="editor-panel">
            <div class="border-b px-6 py-4 sticky top-0 z-40" style="background: var(--editor-card-bg); border-color: var(--editor-border);">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="{{ route('bio.index') }}" @click.prevent="handleNavigation('{{ route('bio.index') }}')" class="hover:opacity-70" style="color: var(--editor-text-muted);" aria-label="Back to bio pages">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-lg font-semibold" style="color: var(--editor-text);" x-text="bioPage.title || 'Untitled'"></h1>
                        <a :href="'{{ url('/') }}/' + bioPage.slug" target="_blank" class="text-sm text-blue-700 dark:text-blue-400 hover:underline flex items-center gap-1">
                            <span x-text="'{{ url('/') }}/' + bioPage.slug"></span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span x-show="hasUnsavedChanges" class="text-xs text-amber-700 dark:text-amber-400 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg>
                        Unsaved
                    </span>
                    <button type="button" data-call="toggleTheme" id="theme-toggle-button" class="p-2 rounded-lg editor-text-muted hover:editor-text hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="Toggle theme" aria-label="Toggle theme">
                        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                    <button @click="saveBioPage()" :disabled="saving" class="btn btn-primary">
                        <svg x-show="!saving" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
                    </button>
                </div>
            </div>
        </div>
        <div class="editor-tabs" role="tablist" aria-label="Editor sections" @keydown.arrow-right.prevent="cycleTab(1)" @keydown.arrow-left.prevent="cycleTab(-1)" @keydown.home.prevent="cycleTab(0, true)" @keydown.end.prevent="cycleTab(-1, true)">
            <button type="button" @click="activeTab = 'profile'" :class="{ 'active': activeTab === 'profile' }" class="editor-tab" role="tab" id="editor-tab-profile" aria-controls="editor-panel-profile" :aria-selected="activeTab === 'profile' ? 'true' : 'false'" :tabindex="activeTab === 'profile' ? 0 : -1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Profile
            </button>
            <button type="button" @click="activeTab = 'links'" :class="{ 'active': activeTab === 'links' }" class="editor-tab" role="tab" id="editor-tab-links" aria-controls="editor-panel-links" :aria-selected="activeTab === 'links' ? 'true' : 'false'" :tabindex="activeTab === 'links' ? 0 : -1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                Build
            </button>
            <button type="button" @click="activeTab = 'socials'" :class="{ 'active': activeTab === 'socials' }" class="editor-tab" role="tab" id="editor-tab-socials" aria-controls="editor-panel-socials" :aria-selected="activeTab === 'socials' ? 'true' : 'false'" :tabindex="activeTab === 'socials' ? 0 : -1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Socials
            </button>
            <button type="button" @click="activeTab = 'appearance'" :class="{ 'active': activeTab === 'appearance' }" class="editor-tab" role="tab" id="editor-tab-appearance" aria-controls="editor-panel-appearance" :aria-selected="activeTab === 'appearance' ? 'true' : 'false'" :tabindex="activeTab === 'appearance' ? 0 : -1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                </svg>
                Appearance
            </button>
            <button type="button" @click="activeTab = 'settings'" :class="{ 'active': activeTab === 'settings' }" class="editor-tab" role="tab" id="editor-tab-settings" aria-controls="editor-panel-settings" :aria-selected="activeTab === 'settings' ? 'true' : 'false'" :tabindex="activeTab === 'settings' ? 0 : -1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </button>
        </div>
        <div class="tab-content">
            <div x-show="activeTab === 'profile'" x-cloak role="tabpanel" id="editor-panel-profile" aria-labelledby="editor-tab-profile" tabindex="0">
                @include('bio.editor.profile-tab')
            </div>
            <div x-show="activeTab === 'links'" x-cloak role="tabpanel" id="editor-panel-links" aria-labelledby="editor-tab-links" tabindex="0">
                @include('bio.editor.links-tab')
            </div>
            <div x-show="activeTab === 'socials'" x-cloak role="tabpanel" id="editor-panel-socials" aria-labelledby="editor-tab-socials" tabindex="0">
                @include('bio.editor.socials-tab')
            </div>
            <div x-show="activeTab === 'appearance'" x-cloak role="tabpanel" id="editor-panel-appearance" aria-labelledby="editor-tab-appearance" tabindex="0">
                @include('bio.editor.appearance-tab')
            </div>
            <div x-show="activeTab === 'settings'" x-cloak role="tabpanel" id="editor-panel-settings" aria-labelledby="editor-tab-settings" tabindex="0">
                @include('bio.editor.settings-tab')
            </div>
        </div>
    </div>
    <div class="preview-panel">
        <div class="preview-header">
            <span class="text-sm font-medium editor-text">Live Preview</span>
            <a :href="'{{ url('/') }}/' + bioPage.slug" target="_blank" 
               class="text-xs text-blue-700 dark:text-blue-400 hover:underline flex items-center gap-1">
                Open in new tab
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            </a>
        </div>
        <div class="preview-frame-container">
            <div class="mobile-frame">
                <div class="mobile-frame-inner">
                    <iframe 
                        title="Live preview of your bio page"
                        x-ref="previewFrame"
                        :src="previewUrl"
                        @load="onPreviewLoad()"
                    ></iframe>
                </div>
            </div>
        </div>
    </div>
    </div><!-- End of editor-container -->
    <!-- All Modals - Outside of editor-container to avoid overflow:hidden clipping -->
    <!-- Link Icon Picker Modal -->
    <div x-show="showIconPicker" x-cloak class="modal-overlay" @click.self="showIconPicker = false" style="z-index: 10001;">
        <div class="modal-content" x-modal="showIconPicker" style="max-width: 560px;" @click.stop>
            <div class="modal-header">
                <h3 class="modal-title">Choose Icon</h3>
                <button type="button" @click.prevent.stop="showIconPicker = false" class="modal-close" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Custom Icon Upload -->
                <div class="p-4" style="border-bottom: 1px solid var(--editor-border); background: var(--editor-card-bg);">
                    <label class="block mb-2">
                        <span class="text-sm font-medium editor-text-muted">Upload Custom Icon</span>
                    </label>
                    <div class="flex gap-2">
                        <input aria-label="Upload custom icon" type="file" accept="image/*" @change="uploadCustomIcon($event)" 
                               class="flex-1 text-sm editor-text-muted file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-600 file:text-white file:font-medium hover:file:bg-blue-700">
                    </div>
                    <p class="text-xs editor-text-muted mt-1">PNG, JPG, or SVG. Max 1MB.</p>
                </div>
                <!-- Search -->
                <div class="p-4" style="border-bottom: 1px solid var(--editor-border);">
                    <input aria-label="Search icons" type="text" x-model="iconSearch" placeholder="Search icons..." class="form-input">
                </div>
                <!-- Icon Grid - 4 columns -->
                <div class="p-4 max-h-80 overflow-y-auto">
                    <div class="grid grid-cols-4 gap-3">
                        <template x-for="(brand, id) in filteredBrands" :key="id">
                            <button @click="setBlockIcon(id)" 
                                    :class="{ 'ring-2 ring-blue-500 var(--editor-hover-bg)': editingBlock && editingBlock.brand === id }"
                                    class="p-3 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 border [border-color:var(--editor-border)] hover:[border-color:var(--editor-border)] transition-colors flex flex-col items-center gap-2">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center" :style="{ backgroundColor: brand.color || '#6b7280' }">
                                    <img :src="'/images/brands/' + (brand.icon || id + '.svg') + '?v=20251212'" class="w-6 h-6" style="filter: brightness(0) invert(1);" data-fallback="hide">
                                </div>
                                <span class="text-xs editor-text-muted truncate w-full text-center" x-text="brand.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <template x-if="editingBlock">
    <div class="modal-overlay" @click.self="closeBlockEditor()">
        <div class="modal-content" x-modal="editingBlock" data-modal-close="closeBlockEditor()" @click.stop>
            <div class="modal-header">
                <h3 class="modal-title">Edit Block</h3>
                <button @click="closeBlockEditor()" class="modal-close" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <template x-if="editingBlock">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3 p-3 rounded-lg" style="background: var(--editor-card-bg)">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center" 
                                 :style="{ backgroundColor: editingBlock?.brand ? getPlatformColor(editingBlock?.brand) : '#6366f1' }">
                                <template x-if="editingBlock?.brand">
                                    <img :src="getPlatformIcon(editingBlock?.brand)" class="w-5 h-5" style="filter: brightness(0) invert(1);">
                                </template>
                                <template x-if="!editingBlock?.brand">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                    </svg>
                                </template>
                            </div>
                            <div>
                                <div class="text-sm font-medium editor-text" x-text="editingBlock?.brand ? brands[editingBlock?.brand]?.name : 'Custom Link'"></div>
                                <div class="text-xs editor-text-muted" x-text="editingBlock?.type"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="bfindex-1" class="form-label">Title</label>
                            <input maxlength="100" id="bfindex-1" type="text" x-model="editingBlock.title" class="form-input" placeholder="Button title" autocomplete="off" data-lpignore="true" data-form-type="other">
                        </div>
                        <template x-if="editingBlock && editingBlock?.type === 'link'">
                            <div class="space-y-4">
                                <!-- Link Mode Selection (Only for Custom Links) -->
                                <template x-if="!editingBlock?.brand">
                                    <div class="form-group mb-4">
                                        <div class="flex gap-6">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="link_mode" value="create" x-model="linkInputMode" class="text-blue-600 focus:ring-blue-500">
                                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Create new</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="link_mode" value="existing" x-model="linkInputMode" class="text-blue-600 focus:ring-blue-500">
                                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Select existing link</span>
                                            </label>
                                        </div>
                                    </div>
                                </template>
                                <div class="form-group">
                                    <!-- Create New Mode -->
                                    <template x-if="editingBlock?.brand || linkInputMode === 'create'">
                                        <div>
                                            <label for="bfindex-2" class="form-label" x-text="editingBlock?.brand ? 'URL' : 'Destination URL'"></label>
                                            <input id="bfindex-2" type="text" :value="editingBlock?.url ?? ''"
                                                @input="if (editingBlock) { editingBlock.url = $event.target.value; validatePlatformUrl(editingBlock) }"
                                                @blur="processUrl(editingBlock, true); validatePlatformUrl(editingBlock)"
                                                @input.debounce.1000ms="fetchUrlTitle(editingBlock)"
                                                class="form-input" 
                                                :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': editingBlock?._urlError }"
                                                :placeholder="editingBlock?.brand ? brands[editingBlock?.brand]?.placeholder : 'ex. https://longurl.com'"
                                                autocomplete="off" data-lpignore="true" data-form-type="other">
                                            <p class="text-xs mt-1" :class="editingBlock?._urlError ? 'text-red-400' : 'editor-text-muted'">
                                                <template x-if="editingBlock?._urlError">
                                                    <span x-text="editingBlock?._urlError"></span>
                                                </template>
                                                <template x-if="!editingBlock?._urlError && !editingBlock?.brand">
                                                    <span>Enter a long URL to automatically shorten it</span>
                                                </template>
                                            </p>
                                        </div>
                                    </template>
                                    <!-- Select Existing Mode -->
                                    <template x-if="!editingBlock?.brand && linkInputMode === 'existing'">
                                        <div class="relative" @click.outside="libraryLinks = []; librarySearch = ''">
                                            <label class="form-label">Select a link</label>
                                            <!-- Selected Link Display -->
                                            <template x-if="editingBlock?.url && !librarySearch">
                                                <div class="mb-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-lg flex items-center justify-between group">
                                                    <div class="overflow-hidden">
                                                        <div class="text-xs text-blue-600 dark:text-blue-400 font-medium mb-0.5">Selected Link</div>
                                                        <div class="text-sm font-medium truncate" x-text="editingBlock?.title || 'Untitled'"></div>
                                                        <div class="text-xs text-slate-500 truncate" x-text="editingBlock?.url"></div>
                                                    </div>
                                                    <button aria-label="Clear selected link" @click="editingBlock.url = ''; editingBlock.title = '';" class="p-1 hover:bg-blue-100 dark:hover:bg-blue-800 rounded text-blue-600 dark:text-blue-400">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </template>
                                            <div class="relative" x-show="!editingBlock?.url || librarySearch">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                    </svg>
                                                </div>
                                                <input aria-label="Search your links" type="text" 
                                                    x-model="librarySearch" 
                                                    @input.debounce.300ms="librarySearch ? fetchLibraryLinks() : (libraryLinks = [])"
                                                    @focus="librarySearch ? fetchLibraryLinks() : null"
                                                    class="form-input pl-10" 
                                                    placeholder="Type to search existing URL...">
                                            </div>
                                            <!-- Inline Results Dropdown -->
                                            <div x-show="librarySearch.length > 0 || (libraryLinks.length > 0 && !editingBlock?.url)" 
                                                 class="absolute z-[60] w-full mt-1 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-60 overflow-y-auto custom-scrollbar">
                                                <template x-if="libraryLoading">
                                                    <div class="p-4 text-center text-sm text-slate-500">Loading...</div>
                                                </template>
                                                <template x-if="!libraryLoading && libraryLinks.length === 0">
                                                    <div class="p-4 text-center text-sm text-slate-500">No links found</div>
                                                </template>
                                                <template x-for="link in libraryLinks" :key="link.id">
                                                    <button @click="selectLibraryLink(link)" 
                                                            class="w-full text-left px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
                                                        <div class="flex justify-between items-start mb-1">
                                                            <span class="font-medium text-sm text-slate-900 dark:text-white group-hover:text-blue-600" x-text="link.title || 'Untitled'"></span>
                                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 font-mono">Short</span>
                                                        </div>
                                                        <div class="text-xs text-slate-500 truncate" x-text="link.url"></div>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <!-- Icon Picker - Only for Custom Links (no brand) -->
                                <template x-if="!editingBlock?.brand">
                                    <div class="form-group">
                                        <label class="form-label">Icon</label>
                                        <button type="button" 
                                                @click.prevent.stop="openIconPickerSafe(editingBlock)" 
                                                class="w-full flex items-center gap-3 p-3 option-btn rounded-lg transition-colors text-left relative z-10"
                                                style="cursor: pointer; pointer-events: auto;">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 transition-colors" 
                                                 :style="{ backgroundColor: '#6366f1' }">
                                                <template x-if="editingBlock?.custom_icon">
                                                    <img :src="editingBlock?.custom_icon" class="w-5 h-5 object-contain">
                                                </template>
                                                <template x-if="!editingBlock?.custom_icon">
                                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                    </svg>
                                                </template>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium editor-text truncate" x-text="editingBlock?.custom_icon ? 'Custom icon' : 'No icon selected'"></div>
                                                <div class="text-xs editor-text-muted">Click to change icon</div>
                                            </div>
                                            <svg class="w-5 h-5 editor-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                                <div class="form-group" x-data="{ showButtonStyle: true }">
                                    <button type="button" @click="showButtonStyle = !showButtonStyle" 
                                            class="w-full flex items-center justify-between p-3 option-btn rounded-lg transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center border-2"
                                                 :style="{
                                                     backgroundColor: isBlockAutoStyle(editingBlock) ? getEffectiveButtonBg() : (editingBlock?.btn_bg_color || getEffectiveButtonBg()),
                                                     borderColor: editingBlock?.btn_border_color || 'transparent'
                                                 }">
                                                <svg class="w-5 h-5" :style="{ color: isBlockAutoStyle(editingBlock) ? getEffectiveButtonText() : (editingBlock?.btn_text_color || getEffectiveButtonText()) }" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                                </svg>
                                            </div>
                                            <div class="text-left">
                                                <div class="text-sm font-medium editor-text">Button Style</div>
                                                <div class="text-xs editor-text-muted" x-text="isBlockAutoStyle(editingBlock) ? 'Auto (following theme)' : 'Custom style'"></div>
                                            </div>
                                        </div>
                                        <svg class="w-5 h-5 editor-text-muted transition-transform" :class="{ 'rotate-180': showButtonStyle }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div x-show="showButtonStyle" x-collapse class="mt-3 space-y-3 p-3 editor-card-bg rounded-lg" style="border: 1px solid var(--editor-border);">
                                        <div class="form-group">
                                            <label class="form-label text-xs">Preview</label>
                                            <div class="flex justify-center p-4 rounded-lg" style="background: var(--editor-bg);">
                                                <div class="px-6 py-3 rounded-lg font-semibold text-center min-w-[200px] flex items-center justify-center gap-2 transition-all"
                                                     :style="{
                                                         backgroundColor: isBlockAutoStyle(editingBlock) ? getEffectiveButtonBg() : (editingBlock?.btn_bg_color || getEffectiveButtonBg()),
                                                         color: isBlockAutoStyle(editingBlock) ? getEffectiveButtonText() : (editingBlock?.btn_text_color || getEffectiveButtonText()),
                                                         border: isBlockAutoStyle(editingBlock) ? 'none' : (editingBlock?.btn_border_color ? '1px solid ' + editingBlock?.btn_border_color : 'none')
                                                     }">
                                                    <template x-if="editingBlock?.brand || editingBlock?.custom_icon">
                                                        <img :src="editingBlock?.custom_icon || getPlatformIcon(editingBlock?.brand)" 
                                                             class="w-5 h-5" 
                                                             :style="{ filter: isBlockAutoStyle(editingBlock) ? getAutoIconFilter() : (editingBlock?.btn_icon_invert == true ? 'brightness(0) invert(1)' : 'none') }">
                                                    </template>
                                                    <span x-text="editingBlock?.title || 'Button Preview'"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Auto Style Checkbox -->
                                        <div class="flex items-center justify-between py-2 px-3 rounded-lg" style="background: var(--editor-bg);">
                                            <div class="flex items-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="checkbox" 
                                                           :checked="isBlockAutoStyle(editingBlock)"
                                                           @change="toggleBlockAutoStyle(editingBlock, $event.target.checked)"
                                                           class="w-4 h-4 rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-0">
                                                    <span class="text-sm font-medium editor-text">Auto (Follow Theme)</span>
                                                </label>
                                            </div>
                                            <span class="text-xs editor-text-muted">Uncheck to manually edit</span>
                                        </div>
                                        <!-- Manual Style Options - only shown when Auto is OFF -->
                                        <template x-if="!isBlockAutoStyle(editingBlock)">
                                            <div class="space-y-3">
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label for="bfindex-3" class="form-label text-xs">Background</label>
                                                        <div class="flex items-center gap-2">
                                                            <input id="bfindex-3" type="color" x-model="editingBlock.btn_bg_color" 
                                                                   :value="editingBlock.btn_bg_color || getEffectiveButtonBg()"
                                                                   class="w-10 h-10 rounded cursor-pointer border-0 bg-transparent"
                                                                   @input="markBlockDirty(editingBlock.id)">
                                                            <input maxlength="30" aria-label="Button background hex value" type="text" x-model="editingBlock.btn_bg_color" 
                                                                   class="form-input text-xs font-mono flex-1" 
                                                                   :placeholder="getEffectiveButtonBg()"
                                                                   @change="markBlockDirty(editingBlock.id)">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <label class="form-label text-xs mb-0">Text Color</label>
                                                            <button type="button" 
                                                                    @click="editingBlock.btn_text_color = getContrastColor(editingBlock.btn_bg_color || getEffectiveButtonBg()); markBlockDirty(editingBlock.id)"
                                                                    class="text-[10px] px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 transition-colors"
                                                                    title="Auto-detect best text color">
                                                                Auto
                                                            </button>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <input aria-label="Button text color" type="color" x-model="editingBlock.btn_text_color" 
                                                                   :value="editingBlock.btn_text_color || getEffectiveButtonText()"
                                                                   class="w-10 h-10 rounded cursor-pointer border-0 bg-transparent">
                                                            <input maxlength="30" aria-label="Button text color hex value" type="text" x-model="editingBlock.btn_text_color" 
                                                                   class="form-input text-xs font-mono flex-1" 
                                                                   :placeholder="getEffectiveButtonText()">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label for="bfindex-4" class="form-label text-xs">Border Color</label>
                                                        <div class="flex items-center gap-2">
                                                            <input id="bfindex-4" type="color" x-model="editingBlock.btn_border_color" 
                                                                   :value="editingBlock.btn_border_color || '#ffffff'"
                                                                   class="w-10 h-10 rounded cursor-pointer border-0 bg-transparent">
                                                            <input maxlength="30" aria-label="Button border color hex value" type="text" x-model="editingBlock.btn_border_color" 
                                                                   class="form-input text-xs font-mono flex-1" placeholder="None">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="form-label text-xs">Icon Style</label>
                                                        <label class="toggle-switch mt-2">
                                                            <input type="checkbox" x-model="editingBlock.btn_icon_invert" aria-label="White icon">
                                                            <span class="toggle-slider"></span>
                                                        </label>
                                                        <span class="text-xs editor-text-muted ml-2">White icon</span>
                                                    </div>
                                                </div>
                                                <div class="flex justify-end pt-2 border-t [border-color:var(--editor-border)]">
                                                    <button type="button" @click="resetBlockToTheme(editingBlock)" 
                                                            class="text-xs text-indigo-400 hover:text-indigo-300 flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                        </svg>
                                                        Reset to Theme Default
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'text'">
                            <div class="form-group">
                                <label class="form-label flex items-center justify-between">
                                    <span>Content</span>
                                    <button type="button" @click="openTextEditor(editingBlock)" 
                                            class="text-xs text-indigo-400 hover:text-indigo-300 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Rich Editor
                                    </button>
                                </label>
                                <textarea aria-label="Text content" :value="editingBlock?.content ?? ''" @input="editingBlock && (editingBlock.content = $event.target.value)" class="form-input form-textarea" rows="3" placeholder="Enter content..."></textarea>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'image'">
                            <div class="space-y-4">
                                <div class="form-group" x-show="editingBlock?.content">
                                    <label class="form-label">Current Image</label>
                                    <div class="relative w-full h-40 rounded-lg overflow-hidden" style="background: var(--editor-input-bg);">
                                        <img :src="editingBlock?.content" class="w-full h-full object-contain">
                                        <button aria-label="Remove image" type="button" @click="editingBlock.content = ''; editingBlock.thumbnail_url = ''" 
                                                class="absolute top-2 right-2 p-1.5 bg-red-600 hover:bg-red-700 rounded-full text-white">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-5" class="form-label">Image Source</label>
                                    <div class="space-y-3">
                                        <div class="flex gap-2">
                                            <input id="bfindex-5" type="url" :value="editingBlock?.content ?? ''" @input="editingBlock && (editingBlock.content = editingBlock.thumbnail_url = $event.target.value)" class="form-input flex-1" placeholder="https://example.com/image.jpg">
                                        </div>
                                        <div class="text-center editor-text-muted text-sm">or</div>
                                        <label class="block p-4 border-2 border-dashed [border-color:var(--editor-border)] hover:border-indigo-500 rounded-lg cursor-pointer transition-colors text-center">
                                            <input aria-label="Upload image" type="file" class="hidden" accept="image/*" @change="uploadBlockImage($event, editingBlock)">
                                            <svg class="w-8 h-8 mx-auto editor-text-muted mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="text-sm editor-text-muted">Click to upload image</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-6" class="form-label">Link URL (optional)</label>
                                    <input maxlength="500" id="bfindex-6" type="url" x-model="editingBlock.url" class="form-input" placeholder="https://example.com">
                                    <p class="text-xs editor-text-muted mt-1">Where should this image link to when clicked?</p>
                                </div>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'youtube'">
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="bfindex-7" class="form-label">YouTube Video URL</label>
                                    <input maxlength="500" id="bfindex-7" type="url" x-model="editingBlock.embed_url" class="form-input" placeholder="https://youtube.com/watch?v=...">
                                    <p class="text-xs editor-text-muted mt-1">Paste the full YouTube video URL</p>
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-8" class="form-label">Title (Optional)</label>
                                    <input maxlength="100" id="bfindex-8" type="text" x-model="editingBlock.title" class="form-input" placeholder="My Video">
                                </div>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'spotify'">
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="bfindex-9" class="form-label">Spotify URL</label>
                                    <input maxlength="500" id="bfindex-9" type="url" x-model="editingBlock.embed_url" class="form-input" placeholder="https://open.spotify.com/track/...">
                                    <p class="text-xs editor-text-muted mt-1">Supports tracks, albums, playlists, and artists</p>
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-10" class="form-label">Title (Optional)</label>
                                    <input maxlength="100" id="bfindex-10" type="text" x-model="editingBlock.title" class="form-input" placeholder="My Music">
                                </div>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'soundcloud'">
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="bfindex-11" class="form-label">SoundCloud URL</label>
                                    <input maxlength="500" id="bfindex-11" type="url" x-model="editingBlock.embed_url" class="form-input" placeholder="https://soundcloud.com/...">
                                    <p class="text-xs editor-text-muted mt-1">Paste any SoundCloud track or profile URL</p>
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-12" class="form-label">Title (Optional)</label>
                                    <input maxlength="100" id="bfindex-12" type="text" x-model="editingBlock.title" class="form-input" placeholder="My Track">
                                </div>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'code'">
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="bfindex-13" class="form-label">Block Title</label>
                                    <input maxlength="100" id="bfindex-13" type="text" x-model="editingBlock.title" class="form-input" placeholder="Code Example">
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-14" class="form-label">Code</label>
                                    <textarea id="bfindex-14" :value="editingBlock?.html_content ?? ''" @input="editingBlock && (editingBlock.html_content = $event.target.value)" class="form-input form-textarea font-mono text-sm" rows="8" placeholder="// Your code here..." style="min-height: 150px;"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-15" class="form-label">Language</label>
                                    <select id="bfindex-15" :value="editingBlock?.content ?? ''" @change="editingBlock && (editingBlock.content = $event.target.value)" class="form-input">
                                        <option value="javascript">JavaScript</option>
                                        <option value="python">Python</option>
                                        <option value="php">PHP</option>
                                        <option value="html">HTML</option>
                                        <option value="css">CSS</option>
                                        <option value="bash">Bash</option>
                                        <option value="json">JSON</option>
                                    </select>
                                </div>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'map'">
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="bfindex-16" class="form-label">Location Title</label>
                                    <input maxlength="100" id="bfindex-16" type="text" x-model="editingBlock.title" class="form-input" placeholder="Our Office">
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-17" class="form-label">Address</label>
                                    <textarea maxlength="500" id="bfindex-17" x-model="editingBlock.map_address" class="form-input form-textarea" rows="2" placeholder="Jl. Example No. 123, Jakarta, Indonesia"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-18" class="form-label">Zoom Level</label>
                                    <input id="bfindex-18" type="range" x-model="editingBlock.map_zoom" min="10" max="18" class="w-full">
                                    <p class="text-sm editor-text-muted text-center" x-text="'Zoom: ' + (editingBlock?.map_zoom || 15)"></p>
                                </div>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'countdown'">
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="bfindex-19" class="form-label">Countdown Label</label>
                                    <input maxlength="100" id="bfindex-19" type="text" x-model="editingBlock.countdown_label" class="form-input" placeholder="Event starts in...">
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-20" class="form-label">Target Date & Time</label>
                                    <input id="bfindex-20" type="datetime-local" :value="toLocalInput(editingBlock.countdown_date)" @input="editingBlock.countdown_date = fromLocalInput($event.target.value)" class="form-input">
                                    <p class="text-xs editor-text-muted mt-1">In your time zone. Visitors see the time left until the same moment.</p>
                                </div>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'header'">
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="bfindex-21" class="form-label">Header Text</label>
                                    <input maxlength="100" id="bfindex-21" type="text" x-model="editingBlock.title" class="form-input" placeholder="Section Header">
                                </div>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'vcard'">
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="bfindex-22" class="form-label">Full Name</label>
                                    <input maxlength="100" id="bfindex-22" type="text" x-model="editingBlock.vcard_name" class="form-input" placeholder="John Doe">
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-23" class="form-label">Phone Number</label>
                                    <input maxlength="30" id="bfindex-23" type="tel" x-model="editingBlock.vcard_phone" class="form-input" placeholder="+1 234 567 8900">
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-24" class="form-label">Email</label>
                                    <input maxlength="100" id="bfindex-24" type="email" x-model="editingBlock.vcard_email" class="form-input" placeholder="john@example.com">
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-25" class="form-label">Company (Optional)</label>
                                    <input maxlength="100" id="bfindex-25" type="text" x-model="editingBlock.vcard_company" class="form-input" placeholder="Company Name">
                                </div>
                            </div>
                        </template>
                        <template x-if="editingBlock?.type === 'html'">
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="bfindex-26" class="form-label">Block Title (Optional)</label>
                                    <input maxlength="100" id="bfindex-26" type="text" x-model="editingBlock.title" class="form-input" placeholder="Custom HTML Block">
                                </div>
                                <div class="form-group">
                                    <label for="bfindex-27" class="form-label">HTML Code</label>
                                    <textarea id="bfindex-27" :value="editingBlock?.html_content ?? ''" @input="editingBlock && (editingBlock.html_content = $event.target.value)" class="form-input form-textarea font-mono text-sm" rows="8" placeholder="<div>Your HTML here...</div>" style="min-height: 150px;"></textarea>
                                    <p class="text-xs editor-text-muted mt-1">⚠️ Be careful with custom HTML - ensure it's safe and valid</p>
                                </div>
                            </div>
                        </template>
                        <!-- Animation Settings - Only for link type blocks -->
                        <template x-if="editingBlock?.type === 'link'">
                            <div class="p-3 rounded-lg space-y-4" style="background: var(--editor-card-bg)">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                    </svg>
                                    <span class="text-sm font-medium editor-text">Animations</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="bfindex-28" class="form-label text-xs mb-1">Entrance Animation</label>
                                        <select id="bfindex-28" x-model="editingBlock.entrance_animation" class="form-input text-xs">
                                            <option value="none">None</option>
                                            <option value="fade">Fade In</option>
                                            <option value="slide-up">Slide Up</option>
                                            <option value="slide-down">Slide Down</option>
                                            <option value="pop">Pop/Zoom</option>
                                            <option value="bounce">Bounce</option>
                                            <option value="flip">Flip</option>
                                            <option value="stagger">Stagger</option>
                                        </select>
                                        <p class="text-[10px] editor-text-muted mt-1">Animation when link first appears</p>
                                    </div>
                                    <div>
                                        <label for="bfindex-29" class="form-label text-xs mb-1">Attention Animation</label>
                                        <select id="bfindex-29" x-model="editingBlock.attention_animation" class="form-input text-xs">
                                            <option value="none">None</option>
                                            <option value="pulse">Pulse</option>
                                            <option value="bounce">Bounce</option>
                                            <option value="shake">Shake</option>
                                            <option value="wiggle">Wiggle</option>
                                            <option value="glow">Glow</option>
                                            <option value="heartbeat">Heartbeat</option>
                                        </select>
                                        <p class="text-[10px] editor-text-muted mt-1">Continuous animation loop</p>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--editor-card-bg)">
                            <div>
                                <div class="text-sm font-medium editor-text">Active</div>
                                <div class="text-xs editor-text-muted">Show this block on your page</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" x-model="editingBlock.is_active" aria-label="Show this block on your page">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </template>
            </div>
            <div class="modal-footer">
                <button @click="closeBlockEditor()" class="btn btn-secondary">Cancel</button>
                <button @click="saveEditingBlock()" 
                        :disabled="!isBlockValid"
                        :class="{ 'opacity-50 cursor-not-allowed': !isBlockValid }"
                        class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
    </template>
    <div x-show="showDiscardModal" x-cloak class="modal-overlay" style="z-index: 9999;">
        <div class="modal-content" x-modal="showDiscardModal" data-modal-close="cancelDiscard()" style="max-width: 400px;" @click.stop>
            <div class="p-6 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-amber-500/20 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold editor-text mb-2">Discard Changes?</h3>
                <p class="editor-text-muted mb-6">You have unsaved changes. Are you sure you want to leave? All changes will be lost.</p>
                <div class="flex gap-3">
                    <button @click="cancelDiscard()" class="flex-1 px-4 py-2.5 btn-secondary font-medium rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button @click="confirmDiscard()" class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
                        Discard Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div x-show="showAddBlockModal" x-cloak class="modal-overlay" @click.self="showAddBlockModal = false">
        <div class="modal-content" x-modal="showAddBlockModal" style="max-width: 600px;" @click.stop>
            <div class="modal-header">
                <h3 class="modal-title">Add Block</h3>
                <button @click="showAddBlockModal = false" class="modal-close" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                @include('bio.editor.add-block-modal')
            </div>
        </div>
    </div>
    <div x-show="showLinkLibraryModal" x-cloak class="modal-overlay" @click.self="showLinkLibraryModal = false" style="z-index: 10000;">
        <div class="modal-content" x-modal="showLinkLibraryModal" style="max-width: 500px;" @click.stop>
            <div class="modal-header">
                <h3 class="modal-title">Link Library</h3>
                <button @click="showLinkLibraryModal = false" class="modal-close" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <input aria-label="Search your links" type="text" x-model="librarySearch" @input.debounce.300ms="fetchLibraryLinks()" placeholder="Search your links..." class="form-input w-full">
                </div>
                <div class="space-y-2 max-h-[300px] overflow-y-auto custom-scrollbar">
                    <template x-if="libraryLoading">
                        <div class="text-center py-8 text-slate-500">
                            <svg class="animate-spin h-6 w-6 mx-auto mb-2 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading links...
                        </div>
                    </template>
                    <template x-if="!libraryLoading && libraryLinks.length === 0">
                        <div class="text-center py-8 text-slate-500">
                            <p>No links found.</p>
                            <p class="text-xs mt-1">Create short links in your dashboard to see them here.</p>
                        </div>
                    </template>
                    <template x-for="link in libraryLinks" :key="link.id">
                        <button @click="selectLibraryLink(link)" class="w-full text-left p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-700 group">
                            <div class="flex justify-between items-start">
                                <div class="font-medium text-sm text-slate-900 dark:text-white group-hover:text-blue-600 transition-colors" x-text="link.title || 'Untitled Link'"></div>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 font-mono">Short</span>
                            </div>
                            <div class="text-xs text-blue-600 dark:text-blue-400 font-mono mt-1" x-text="link.short_url"></div>
                            <div class="text-xs text-slate-400 truncate mt-0.5" x-text="link.url"></div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
    <div x-show="showSocialPicker" x-cloak class="modal-overlay" @click.self="showSocialPicker = false">
        <div class="modal-content" x-modal="showSocialPicker" style="max-width: 480px;" @click.stop>
            <div class="modal-header">
                <h3 class="modal-title">Add Social Icon</h3>
                <button @click="showSocialPicker = false" class="modal-close" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="p-4 border-b [border-color:var(--editor-border)]">
                    <input aria-label="Search platforms" type="text" x-model="socialSearch" placeholder="Search platforms..." 
                           class="form-input" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
                </div>
                <div class="max-h-80 overflow-y-auto">
                    <template x-for="(platform, id) in filteredPlatforms" :key="id">
                        <button @click="selectSocialPlatform(id)" 
                                class="w-full flex items-center gap-3 px-4 py-3 hover:bg-slate-100 dark:hover:bg-slate-700/50 border-b [border-color:var(--editor-border)]/50 transition-colors">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center" 
                                 :style="{ backgroundColor: platform.color || '#6b7280' }">
                                <img :src="'/images/brands/' + (platform.icon || id + '.svg') + '?v=20251212'" 
                                     class="w-6 h-6" style="filter: brightness(0) invert(1);" 
                                     data-fallback-src="/images/brands/link.svg?v=20251212">
                            </div>
                            <div class="flex-1 text-left">
                                <div class="text-sm font-medium editor-text" x-text="platform.name"></div>
                                <div class="text-xs editor-text-muted" x-text="platform.placeholder"></div>
                            </div>
                            <svg class="w-5 h-5 editor-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
    <!-- Social Link Editor Modal -->
    <div x-show="showSocialEditor" x-cloak class="modal-overlay" @click.self="cancelSocialEdit()">
        <template x-if="editingSocial">
            <div class="modal-content" x-modal="showSocialEditor" data-modal-close="cancelSocialEdit()" style="max-width: 420px;" @click.stop>
                <div class="modal-header">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center" 
                             :style="{ backgroundColor: platforms[editingSocial.platform]?.color || '#6b7280' }">
                            <img :src="getIconUrl(editingSocial.platform || 'link', 'ffffff')" 
                                 class="w-6 h-6"
                                 x-on:error="if (!$el.dataset.fallbackDone) { $el.dataset.fallbackDone = 1; $el.src = '/images/brands/' + (editingSocial.platform || 'link') + '.svg?v=20251212'; }">
                        </div>
                        <h3 class="modal-title" x-text="'Add ' + (platforms[editingSocial.platform]?.name || 'Social') + ' Link'"></h3>
                    </div>
                    <button @click="cancelSocialEdit()" class="modal-close" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="bfindex-30" class="form-label" x-text="platforms[editingSocial.platform]?.name + ' Username or URL'"></label>
                        <input id="bfindex-30" type="text" 
                               x-model="editingSocial.value"
                               :placeholder="platforms[editingSocial.platform]?.placeholder || 'Enter username or URL'"
                               class="form-input"
                               autocomplete="off" data-lpignore="true" data-1p-ignore="true"
                               @keydown.enter="saveSocialEdit()">
                        <p class="text-xs editor-text-muted mt-2">
                            <span x-text="'Example: ' + (platforms[editingSocial.platform]?.placeholder || 'username')"></span>
                        </p>
                    </div>
                </div>
                <div class="modal-footer flex justify-end gap-3">
                    <button @click="cancelSocialEdit()" class="btn btn-secondary">Cancel</button>
                    <button @click="saveSocialEdit()" class="btn btn-primary" :disabled="!editingSocial.value?.trim()">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save
                    </button>
                </div>
            </div>
        </template>
    </div>
    <div x-show="showTextEditor" x-cloak class="modal-overlay" @click.self="showTextEditor = false">
        <div class="modal-content" x-modal="showTextEditor" style="max-width: 600px;" @click.stop>
            <div class="modal-header">
                <h3 class="modal-title">Edit Text Block</h3>
                <button @click="showTextEditor = false" class="modal-close" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="flex flex-wrap gap-1 p-2 rounded-t-lg" style="background: var(--editor-card-bg);">
                    <button aria-label="Bold" @click="formatText('bold')" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded" title="Bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"/>
                        </svg>
                    </button>
                    <button aria-label="Italic" @click="formatText('italic')" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded" title="Italic">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <line x1="19" y1="4" x2="10" y2="4" stroke-width="2"/>
                            <line x1="14" y1="20" x2="5" y2="20" stroke-width="2"/>
                            <line x1="15" y1="4" x2="9" y2="20" stroke-width="2"/>
                        </svg>
                    </button>
                    <button aria-label="Underline" @click="formatText('underline')" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded" title="Underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v7a5 5 0 0010 0V4"/>
                            <line x1="4" y1="21" x2="20" y2="21" stroke-width="2"/>
                        </svg>
                    </button>
                    <button aria-label="Strikethrough" @click="formatText('strikeThrough')" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded" title="Strikethrough">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.5 9.5c0-2.5-2-4.5-4.5-4.5s-4.5 2-4.5 4.5"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.5 14.5c0 2.5 2 4.5 4.5 4.5s4.5-2 4.5-4.5"/>
                            <line x1="4" y1="12" x2="20" y2="12" stroke-width="2"/>
                        </svg>
                    </button>
                    <div class="w-px h-6 mx-1 self-center" style="background: var(--editor-border);"></div>
                    <button aria-label="Align Left" @click="formatText('justifyLeft')" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded" title="Align Left">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <line x1="3" y1="6" x2="21" y2="6" stroke-width="2"/>
                            <line x1="3" y1="12" x2="15" y2="12" stroke-width="2"/>
                            <line x1="3" y1="18" x2="18" y2="18" stroke-width="2"/>
                        </svg>
                    </button>
                    <button aria-label="Align Center" @click="formatText('justifyCenter')" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded" title="Align Center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <line x1="3" y1="6" x2="21" y2="6" stroke-width="2"/>
                            <line x1="6" y1="12" x2="18" y2="12" stroke-width="2"/>
                            <line x1="4" y1="18" x2="20" y2="18" stroke-width="2"/>
                        </svg>
                    </button>
                    <button aria-label="Align Right" @click="formatText('justifyRight')" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded" title="Align Right">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <line x1="3" y1="6" x2="21" y2="6" stroke-width="2"/>
                            <line x1="9" y1="12" x2="21" y2="12" stroke-width="2"/>
                            <line x1="6" y1="18" x2="21" y2="18" stroke-width="2"/>
                        </svg>
                    </button>
                </div>
                <div x-ref="richTextEditor" 
                     contenteditable="true" 
                     class="min-h-[250px] p-4 rounded-b-lg focus:outline-none editor-text"
                     style="min-height: 200px; background: var(--editor-input-bg); border: 1px solid var(--editor-border); border-top: none;"
                     @input="updateTextBlockContent($event)">
                </div>
            </div>
            <div class="modal-footer">
                <button @click="showTextEditor = false" class="btn btn-secondary">Cancel</button>
                <button @click="saveTextBlock()" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
    <div x-show="showImageUploader" x-cloak class="modal-overlay" @click.self="showImageUploader = false">
        <div class="modal-content" x-modal="showImageUploader" style="max-width: 500px;" @click.stop>
            <div class="modal-header">
                <h3 class="modal-title">Add Image</h3>
                <button @click="showImageUploader = false" class="modal-close" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="flex gap-2 mb-4">
                    <button @click="imageUploadTab = 'upload'" 
                            :class="imageUploadTab === 'upload' ? 'bg-blue-600 text-white' : 'btn-secondary'"
                            class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors">
                        Upload File
                    </button>
                    <button @click="imageUploadTab = 'url'" 
                            :class="imageUploadTab === 'url' ? 'bg-blue-600 text-white' : 'btn-secondary'"
                            class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors">
                        From URL
                    </button>
                </div>
                <div x-show="imageUploadTab === 'upload'">
                    <div class="border-2 border-dashed [border-color:var(--editor-border)] rounded-xl p-8 text-center">
                        <input aria-label="Upload image" type="file" accept="image/*" class="hidden" x-ref="imageBlockInput" @change="uploadImageBlock($event)">
                        <svg class="w-12 h-12 mx-auto editor-text-muted mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="editor-text-muted mb-2">Drag and drop or click to upload</p>
                        <button @click="$refs.imageBlockInput.click()" class="btn btn-secondary">Choose File</button>
                    </div>
                </div>
                <!-- URL Tab -->
                <div x-show="imageUploadTab === 'url'">
                    <div class="form-group">
                        <label for="bfindex-31" class="form-label">Image URL</label>
                        <input id="bfindex-31" type="url" x-model="imageBlockUrl" class="form-input" placeholder="https://example.com/image.jpg">
                    </div>
                    <div class="form-group">
                        <label for="bfindex-32" class="form-label">Alt Text (optional)</label>
                        <input id="bfindex-32" type="text" x-model="imageBlockAlt" class="form-input" placeholder="Image description">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button @click="showImageUploader = false" class="btn btn-secondary">Cancel</button>
                <button @click="saveImageBlock()" class="btn btn-primary">Add Image</button>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts-head')
<script nonce="{{ Vite::cspNonce() }}">
// Editor API URLs follow the *saved* slug, so renaming the page doesn't break later requests.
window.bioEditorSlug = @json($bioPage->slug);
window.bioEditorUrl = (path = '') => '/bio/' + encodeURIComponent(window.bioEditorSlug) + path;
// Define bioEditorV2 before Alpine initializes
window.bioEditorV2 = function() {
    return {
        // State
        activeTab: 'links',
        previewMode: 'mobile',
        saving: false,
        hasUnsavedChanges: false,
        originalDataHash: '',
        showDiscardModal: false,
        announcement: '',
        pendingNavigation: null,
        editingBlock: null,
        showAddBlockModal: false,
        blockCategoryFilter: 'all',
        blockSearch: '',
        showSocialPicker: false,
        showSocialEditor: false,
        editingSocial: null,
        showTextEditor: false,
        showImageUploader: false,
        showIconPicker: false,
        showPassword: false,
        socialSearch: '',
        iconSearch: '',
        imageUploadTab: 'upload',
        imageBlockUrl: '',
        imageBlockAlt: '',
        imageBlockLink: '',
        textEditorBlock: null,
        iconPickerBlock: null,
        editingTextBlock: null,
        urlInputTimeout: null,
        previewUrl: '{{ route("bio.preview", $bioPage) }}',
        previewReady: false,
        deletedBlockIds: [],
        // Last server revision this tab has seen; a save from another tab makes the next save fail with 409 (L2).
        _baseUpdatedAt: null,
        _saveInFlight: false,
        _saveQueued: false,
        _orderDirty: false,
        // Link Library State
        showLinkLibraryModal: false,
        librarySearch: '',
        libraryLinks: [],
        libraryLoading: false,
        linkInputMode: 'create', // 'create' or 'existing'
        dirtyBlockIds: new Set(), // Track which blocks have been modified
        // Data
        platforms: @json(config('brands.platforms', [])),
        bioPage: @json($bioPage),
        blocks: @json($bioPage->links->sortBy('order')->values()),
        brands: @json(config('brands.platforms', [])),
        // Themes - using hex colors only for input compatibility
        themes: @json(\App\Support\BioThemes::picker()),
        // Sortable instances
        blocksSortable: null,
        socialsSortable: null,
        // Computed
        get filteredPlatforms() {
            const search = this.socialSearch.toLowerCase();
            if (!search) return this.platforms;
            return Object.fromEntries(
                Object.entries(this.platforms).filter(([key, val]) => 
                    val.name.toLowerCase().includes(search) || key.includes(search)
                )
            );
        },
        get filteredBrands() {
            const search = this.iconSearch.toLowerCase();
            if (!search) return this.brands;
            return Object.fromEntries(
                Object.entries(this.brands).filter(([key, val]) => 
                    val.name.toLowerCase().includes(search) || key.includes(search)
                )
            );
        },
        get isBlockValid() {
            if (!this.editingBlock) return false;
            if (this.editingBlock._urlError) return false;
            if (this.editingBlock.type === 'link') {
                return !!(this.editingBlock.url && String(this.editingBlock.url).trim());
            }
            return true;
        },
        init() {
            // Expose bioPage to window for child components (like qrGenerator)
            window.bioEditorData = { bioPage: this.bioPage };
            this.$nextTick(() => {
                this.initSortable();
            });
            // Initialize social_links if not exists
            if (!this.bioPage.social_links) {
                this.bioPage.social_links = [];
            }
            // Stable client-side keys for x-for / drag sorting (stripped before saving)
            this.bioPage.social_links.forEach(s => { s._uid ??= this.uid(); });
            this._baseUpdatedAt = this.bioPage.updated_at || null;
            // Store original data hash for change detection
            this.originalDataHash = this.getDataHash();
            // Listen for preview ready
            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin) return;
                if (event.data?.type === 'preview-ready') {
                    this.previewReady = true;
                    this.sendPreviewUpdate();
                }
            });
            // Watch for changes and update preview (debounced for performance)
            this.$watch('bioPage', () => {
                this.debouncedPreviewUpdate();
                this.debouncedCheckForChanges();
            }, { deep: true });
            this.$watch('blocks', () => {
                this.debouncedPreviewUpdate();
                this.debouncedCheckForChanges();
            }, { deep: true });
            // Warn before leaving with unsaved changes (fallback for browser refresh/close)
            window.addEventListener('beforeunload', (e) => {
                if (this.hasUnsavedChanges) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });
            // Intercept F5 and Ctrl+R to show custom modal instead of browser dialog
            const self = this;
            document.addEventListener('keydown', (e) => {
                if (self.hasUnsavedChanges) {
                    // F5 key
                    if (e.key === 'F5') {
                        e.preventDefault();
                        self.pendingNavigation = window.location.href;
                        self.showDiscardModal = true;
                        return;
                    }
                    // Ctrl+R or Cmd+R (reload)
                    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                        e.preventDefault();
                        self.pendingNavigation = window.location.href;
                        self.showDiscardModal = true;
                        return;
                    }
                    // Ctrl+W or Cmd+W (close tab) - browser may not allow this
                    if ((e.ctrlKey || e.metaKey) && e.key === 'w') {
                        e.preventDefault();
                        self.pendingNavigation = null;
                        self.showDiscardModal = true;
                        return;
                    }
                }
            });
            // Intercept all link clicks to show custom modal
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a[href]');
                if (link && this.hasUnsavedChanges) {
                    const href = link.getAttribute('href');
                    // Skip if it's a javascript: link, anchor, or external link that opens in new tab
                    if (href && !href.startsWith('#') && !href.startsWith('javascript:') && link.target !== '_blank') {
                        e.preventDefault();
                        this.handleNavigation(href);
                    }
                }
            });
            // Handle browser back/forward button with custom modal
            window.addEventListener('popstate', (e) => {
                if (this.hasUnsavedChanges) {
                    // Push state back to prevent navigation
                    history.pushState(null, '', window.location.href);
                    this.pendingNavigation = document.referrer || '/bio';
                    this.showDiscardModal = true;
                }
            });
            // Push initial state to enable popstate handling
            history.pushState(null, '', window.location.href);
        },
        uid() {
            return Math.random().toString(36).slice(2, 10);
        },
        getDataHash() {
            return JSON.stringify({ bioPage: this.bioPage, blocks: this.blocks });
        },
        checkForChanges() {
            this.hasUnsavedChanges = this.getDataHash() !== this.originalDataHash;
        },
        // Debounced version for watchers - reduces CPU usage
        debouncedCheckForChanges() {
            if (this._checkTimeout) clearTimeout(this._checkTimeout);
            this._checkTimeout = setTimeout(() => this.checkForChanges(), 100);
        },
        // Debounced preview update to prevent UI lag
        debouncedPreviewUpdate() {
            if (this._previewTimeout) clearTimeout(this._previewTimeout);
            this._previewTimeout = setTimeout(() => this.sendPreviewUpdate(), 50);
        },
        handleNavigation(url) {
            if (this.hasUnsavedChanges) {
                this.pendingNavigation = url;
                this.showDiscardModal = true;
            } else {
                window.location.href = url;
            }
        },
        confirmDiscard() {
            this.hasUnsavedChanges = false;
            this.showDiscardModal = false;
            if (this.pendingNavigation) {
                window.location.href = this.pendingNavigation;
            }
        },
        cancelDiscard() {
            this.showDiscardModal = false;
            this.pendingNavigation = null;
        },
        announce(message) {
            // Clear first so repeating the same message is still read out.
            this.announcement = '';
            this.$nextTick(() => { this.announcement = message; });
        },
        // Keyboard alternative to drag and drop (arrow keys on the drag handle).
        moveBlock(index, step) {
            const to = index + step;
            if (to < 0 || to >= this.blocks.length) return;
            const [moved] = this.blocks.splice(index, 1);
            this.blocks.splice(to, 0, moved);
            this.blocks.forEach((b, i) => b.order = i);
            this.debouncedSaveOrder();
            this.announce(`${this.getBlockDisplayTitle(moved)} moved to position ${to + 1} of ${this.blocks.length}`);
            this.$nextTick(() => document.querySelectorAll('#blocks-list .block-drag-handle')[to]?.focus());
        },
        moveSocial(index, step) {
            const list = this.bioPage.social_links || [];
            const to = index + step;
            if (to < 0 || to >= list.length) return;
            const [moved] = list.splice(index, 1);
            list.splice(to, 0, moved);
            this.announce(`${this.platforms[moved.platform]?.name || moved.platform} moved to position ${to + 1} of ${list.length}`);
            this.$nextTick(() => document.querySelectorAll('#socials-list .social-drag-handle')[to]?.focus());
        },
        cycleTab(step, absolute = false) {
            const tabs = ['profile', 'links', 'socials', 'appearance', 'settings'];
            // Arrow keys move from the focused tab, which may differ from the open one.
            const focused = document.activeElement?.id?.replace('editor-tab-', '');
            const current = tabs.indexOf(tabs.includes(focused) ? focused : this.activeTab);
            const next = absolute
                ? (step < 0 ? tabs.length - 1 : 0)
                : (current + step + tabs.length) % tabs.length;
            this.activeTab = tabs[next];
            this.$nextTick(() => document.getElementById('editor-tab-' + tabs[next])?.focus());
        },
        initSortable() {
            // Tabs use x-show, so the list containers exist for the editor's lifetime:
            // create each Sortable once and never destroy/re-create it after a drop.
            this.$nextTick(() => {
                const blocksEl = document.getElementById('blocks-list');
                if (blocksEl && !this.blocksSortable) {
                    this.blocksSortable = this.makeSortable(blocksEl, '.block-drag-handle', (from, to) => {
                        const [moved] = this.blocks.splice(from, 1);
                        this.blocks.splice(to, 0, moved);
                        this.blocks.forEach((b, i) => b.order = i);
                        this.debouncedSaveOrder();
                    });
                }
                const socialsEl = document.getElementById('socials-list');
                if (socialsEl && !this.socialsSortable) {
                    this.socialsSortable = this.makeSortable(socialsEl, '.social-drag-handle', (from, to) => {
                        const list = this.bioPage.social_links;
                        const [moved] = list.splice(from, 1);
                        list.splice(to, 0, moved);
                    });
                }
            });
        },
        makeSortable(el, handle, onMove) {
            return Sortable.create(el, {
                draggable: '.block-item',
                handle,
                animation: 150,
                ghostClass: 'block-ghost',
                chosenClass: 'block-chosen',
                // Pointer-based dragging behaves the same with mouse and touch and
                // avoids the browser's native drag image flicker.
                forceFallback: true,
                fallbackTolerance: 4,
                scroll: true,
                bubbleScroll: true,
                fallbackOnBody: true,
                // The drag ghost is a clone of the item appended to <body>; keep Alpine
                // from initialising it outside its x-for scope ("item is not defined").
                onStart: () => { document.body._x_ignoreMutationObserver = true; },
                onEnd: (evt) => {
                    delete document.body._x_ignoreMutationObserver;
                    const { item, from, oldIndex, newIndex } = evt;
                    if (oldIndex === undefined || newIndex === undefined || oldIndex === newIndex) return;
                    // Put the node back where it was: Alpine's keyed x-for moves it
                    // once the array changes, so DOM and data never disagree.
                    const siblings = Array.from(from.children).filter(c => c !== item && c.tagName !== 'TEMPLATE');
                    from.insertBefore(item, siblings[oldIndex] ?? null);
                    onMove(oldIndex, newIndex);
                }
            });
        },
        debouncedSaveOrder() {
            clearTimeout(this._orderTimeout);
            this._orderTimeout = setTimeout(() => this.saveBlocksOrder(), 400);
        },
        // Preview
        sendPreviewUpdate() {
            if (!this.previewReady) return;
            const frame = this.$refs.previewFrame;
            if (frame && frame.contentWindow) {
                try {
                    // Clone data to avoid proxy issues with postMessage
                    const bioPageData = JSON.parse(JSON.stringify(Alpine.raw(this.bioPage)));
                    const blocksData = JSON.parse(JSON.stringify(Alpine.raw(this.blocks)));
                    // Helper to format avatar URL
                    const formatAvatarUrl = (url) => {
                        if (!url) return null;
                        if (url.startsWith('/storage/') || url.startsWith('http')) return url;
                        return '/storage/' + url;
                    };
                    // Map to preview expected format
                    const previewData = {
                        profile: {
                            displayName: bioPageData.title || 'Your Name',
                            bio: bioPageData.bio || '',
                            avatar: formatAvatarUrl(bioPageData.avatar_url),
                            avatarShape: bioPageData.avatar_shape || 'circle',
                            badge: bioPageData.badge || null,
                            badgeColor: bioPageData.badge_color || '#3b82f6'
                        },
                        blocks: blocksData,
                        socials: {
                            position: bioPageData.social_icons_position === 'bottom_page' ? 'bottom' : 'top',
                            // Same rule as the public page: shown unless switched off, and only with a value.
                            items: (bioPageData.social_links || []).filter(s => s && ![false, 0, '0', 'false'].includes(s.enabled ?? s.is_active ?? true) && String(s.value || s.url || '').trim() !== '').map((s, i) => ({
                                id: i,
                                platform: s.platform,
                                url: s.value || s.url || ''
                            }))
                        },
                        design: {
                            // Avatar
                            avatarShape: bioPageData.avatar_shape || 'circle',
                            // Background
                            backgroundColor: bioPageData.background_type === 'solid' ? 
                                (bioPageData.background_value || bioPageData.background_color || '#f8fafc') : 
                                (bioPageData.background_color || '#f8fafc'),
                            backgroundType: bioPageData.background_type || 'solid',
                            backgroundGradient: bioPageData.background_type === 'gradient' ? 
                                (bioPageData.background_value || bioPageData.background_gradient) : null,
                            backgroundImage: bioPageData.background_type === 'image' ? 
                                (bioPageData.background_value && (bioPageData.background_value.startsWith('http') || bioPageData.background_value.startsWith('/storage/'))
                                    ? bioPageData.background_value 
                                    : (bioPageData.background_value && !bioPageData.background_value.startsWith('linear-gradient') && !bioPageData.background_value.startsWith('#')
                                        ? '/storage/' + bioPageData.background_value 
                                        : (bioPageData.background_image 
                                            ? (bioPageData.background_image.startsWith('http') || bioPageData.background_image.startsWith('/storage/') 
                                                ? bioPageData.background_image 
                                                : '/storage/' + bioPageData.background_image)
                                            : null))) 
                                : null,
                            // Typography
                            textColor: bioPageData.text_color || '#1e293b',
                            titleColor: bioPageData.title_color || bioPageData.text_color || @js(config('bio-themes.defaults.title')),
                            bioColor: bioPageData.bio_color || @js(config('bio-themes.defaults.bio')),
                            fontFamily: bioPageData.font_family || 'inter',
                            // Buttons
                            buttonBgColor: bioPageData.link_bg_color || bioPageData.button_bg_color || @js(config('bio-themes.defaults.link_bg')),
                            buttonTextColor: bioPageData.link_text_color || bioPageData.button_text_color || @js(config('bio-themes.defaults.link_text')),
                            buttonShape: bioPageData.block_shape || bioPageData.button_shape || 'rounded',
                            buttonShadow: bioPageData.block_shadow || bioPageData.button_shadow || 'md',
                            // Animations
                            hoverEffect: bioPageData.hover_effect || 'none',
                            backgroundAnimation: bioPageData.background_animation || 'none',
                            // Theme
                            theme: bioPageData.theme || 'default'
                        },
                        settings: {
                            hideBranding: bioPageData.hide_branding || false
                        }
                    };
                    frame.contentWindow.postMessage({
                        type: 'preview-update',
                        data: previewData
                    }, window.location.origin);
                } catch (e) {
                    console.warn('Preview update failed:', e);
                }
            }
        },
        refreshPreview() {
            const frame = this.$refs.previewFrame;
            if (frame) frame.src = frame.src;
        },
        onPreviewLoad() {
        },
        // Block Management
        addBlock(type) {
            this.linkInputMode = 'create';
            const newBlock = {
                id: Date.now(),
                type: type,
                title: this.getDefaultBlockTitle(type),
                url: '',
                content: '',
                thumbnail_url: null,
                is_active: true,
                order: this.blocks.length,
                brand: null,
                is_new: true, // Mark as new
                // Advanced block fields
                embed_url: '',
                countdown_date: '',
                countdown_label: '',
                map_address: '',
                map_zoom: 15,
                vcard_name: '',
                vcard_phone: '',
                vcard_email: '',
                vcard_company: '',
                faq_items: [],
                html_content: ''
            };
            this.blocks.push(newBlock);
            if (['link', 'header', 'text', 'video', 'music', 'vcard', 'html', 'countdown', 'map', 'faq', 'youtube', 'spotify', 'soundcloud', 'code', 'image'].includes(type)) {
                this.editingBlock = JSON.parse(JSON.stringify(newBlock));
            }
            this.showAddBlockModal = false;
        },
        getDefaultBlockTitle(type) {
            const titles = {
                'header': 'Header',
                'text': 'Text Block',
                'video': 'Video',
                'music': 'Music',
                'vcard': 'Contact Card',
                'html': 'Custom HTML',
                'countdown': 'Countdown Timer',
                'map': 'Location',
                'faq': 'FAQ'
            };
            return titles[type] || '';
        },
        addBrandBlock(brandId) {
            this.linkInputMode = 'create';
            const brand = this.brands[brandId];
            const newBlock = {
                id: Date.now(),
                type: 'link',
                title: brand?.name || brandId,
                url: '',
                content: '',
                thumbnail_url: null,
                is_active: true,
                order: this.blocks.length,
                brand: brandId,
                is_new: true // Mark as new
            };
            this.blocks.push(newBlock);
            this.editingBlock = JSON.parse(JSON.stringify(newBlock));
            this.showAddBlockModal = false;
        },
        addDividerInstant() {
            const newBlock = {
                id: Date.now(),
                type: 'divider',
                title: 'Divider',
                url: '',
                content: '',
                thumbnail_url: null,
                is_active: true,
                order: this.blocks.length
            };
            this.blocks.push(newBlock);
            this.showNotification('Divider added!', 'success');
        },
        openLinkLibrary() {
            this.showLinkLibraryModal = true;
            this.fetchLibraryLinks();
        },
        fetchLibraryLinks() {
            this.libraryLoading = true;
            fetch('/api/links?query=' + encodeURIComponent(this.librarySearch), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                this.libraryLinks = data.data || data;
                this.libraryLoading = false;
            })
            .catch(err => {
                console.error('Error fetching links:', err);
                this.libraryLoading = false;
            });
        },
        selectLibraryLink(link) {
            if (this.editingBlock) {
                this.editingBlock.url = link.short_url;
                this.editingBlock.title = this.editingBlock.title || link.title;
                this.editingBlock._urlError = null;
                // Clear search results
                this.librarySearch = '';
                this.libraryLinks = [];
            }
            this.showLinkLibraryModal = false;
        },
        closeBlockEditor() {
            if (this.editingBlock) {
                const index = this.blocks.findIndex(b => b.id === this.editingBlock.id);
                if (index !== -1) {
                    // If it's marked as new, remove it
                    if (this.blocks[index].is_new) {
                        this.blocks.splice(index, 1);
                    }
                }
            }
            this.editingBlock = null;
        },
        async saveEditingBlock() {
            if (!this.editingBlock) return;
            if (this.editingBlock.type === 'link') {
                if (!this.editingBlock.title || !String(this.editingBlock.title).trim()) {
                    this.showNotification('Title is required', 'error');
                    return;
                }
                this.processUrl(this.editingBlock, true);
                let urlToCheck = String(this.editingBlock.url || '').trim();
                if (!urlToCheck) {
                    this.showNotification('URL is required', 'error');
                    return;
                }
                if (!/^[a-z][a-z0-9+.\-]*:/i.test(urlToCheck)) {
                    urlToCheck = 'https://' + urlToCheck;
                }
                this.editingBlock.url = urlToCheck;
                // Auto-shorten custom web links (not brand/mailto/tel links, not our own short links)
                const isWeb = /^https?:\/\//i.test(urlToCheck);
                if (isWeb && !this.editingBlock.brand && !urlToCheck.includes(window.location.host)) {
                    this.saving = true;
                    try {
                        const response = await fetch('/api/bio/shorten', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                url: urlToCheck,
                                title: this.editingBlock.title
                            })
                        });
                        const data = await response.json().catch(() => ({}));
                        if (response.ok && data.short_url) {
                            this.editingBlock.url = data.short_url;
                        } else if (response.status === 422) {
                            this.editingBlock._urlError = data.message || 'This URL is not allowed.';
                            this.showNotification(this.editingBlock._urlError, 'error');
                            return;
                        } else if (response.status === 429) {
                            this.showNotification('Too many links created. Please wait a moment.', 'error');
                            return;
                        }
                        // Other failures: keep the long URL, the block still saves.
                    } catch (e) {
                        console.error('Auto-shorten failed:', e);
                    } finally {
                        this.saving = false;
                    }
                }
                if (this.editingBlock.brand && !this.validateBrandUrl(this.editingBlock)) {
                    const brandName = this.brands[this.editingBlock.brand]?.name || this.editingBlock.brand;
                    this.showNotification(`URL doesn't match ${brandName}. Please enter a valid ${brandName} URL.`, 'error');
                    return;
                }
            }
            if (this.editingBlock.type === 'youtube') {
                if (!this.editingBlock.embed_url || !this.editingBlock.embed_url.trim()) {
                    this.showNotification('YouTube URL is required', 'error');
                    return;
                }
                if (!this.editingBlock.embed_url.match(/youtube\.com|youtu\.be/i)) {
                    this.showNotification('Please enter a valid YouTube URL', 'error');
                    return;
                }
            }
            if (this.editingBlock.type === 'spotify') {
                if (!this.editingBlock.embed_url || !this.editingBlock.embed_url.trim()) {
                    this.showNotification('Spotify URL is required', 'error');
                    return;
                }
                if (!this.editingBlock.embed_url.match(/spotify\.com/i)) {
                    this.showNotification('Please enter a valid Spotify URL', 'error');
                    return;
                }
            }
            if (this.editingBlock.type === 'soundcloud') {
                if (!this.editingBlock.embed_url || !this.editingBlock.embed_url.trim()) {
                    this.showNotification('SoundCloud URL is required', 'error');
                    return;
                }
                if (!this.editingBlock.embed_url.match(/soundcloud\.com/i)) {
                    this.showNotification('Please enter a valid SoundCloud URL', 'error');
                    return;
                }
            }
            if (this.editingBlock.type === 'map') {
                if (!this.editingBlock.map_address || !this.editingBlock.map_address.trim()) {
                    this.showNotification('Address is required for map block', 'error');
                    return;
                }
            }
            if (this.editingBlock.type === 'countdown') {
                if (!this.editingBlock.countdown_date) {
                    this.showNotification('Target date is required for countdown', 'error');
                    return;
                }
            }
            if (this.editingBlock.type === 'header' && (!this.editingBlock.title || !this.editingBlock.title.trim())) {
                this.showNotification('Header title is required', 'error');
                return;
            }
            const index = this.blocks.findIndex(b => b.id === this.editingBlock.id);
            if (index !== -1) {
                const updatedBlock = { ...this.editingBlock };
                delete updatedBlock.is_new; // Remove new flag
                this.blocks[index] = updatedBlock;
                // Mark block as dirty so it gets saved
                this.markBlockDirty(updatedBlock.id);
            }
            this.editingBlock = null;
        },
        toggleBlock(index) {
            this.blocks[index].is_active = !this.blocks[index].is_active;
            this.markBlockDirty(this.blocks[index].id);
        },
        // Social Icons - Step 1: Select platform
        selectSocialPlatform(platformId) {
            if (!this.bioPage.social_links) {
                this.bioPage.social_links = [];
            }
            if (this.bioPage.social_links.length >= 5) {
                this.showNotification('Maximum 5 social icons allowed', 'error');
                return;
            }
            // Create temporary social object for editing
            this.editingSocial = {
                platform: platformId,
                value: '',
                enabled: true
            };
            this.showSocialPicker = false;
            this.socialSearch = '';
            this.showSocialEditor = true;
        },
        // Social Icons - Step 2: Save the social link
        saveSocialEdit() {
            if (!this.editingSocial?.value?.trim()) {
                this.showNotification('Please enter a username or URL', 'error');
                return;
            }
            if (!this.bioPage.social_links) {
                this.bioPage.social_links = [];
            }
            this.bioPage.social_links.push({
                _uid: this.uid(),
                platform: this.editingSocial.platform,
                value: this.editingSocial.value.trim(),
                enabled: true
            });
            this.showSocialEditor = false;
            this.editingSocial = null;
            this.showNotification('Social icon added!', 'success');
        },
        // Social Icons - Cancel editing
        cancelSocialEdit() {
            this.showSocialEditor = false;
            this.editingSocial = null;
        },
        // Legacy addSocial for backwards compatibility
        addSocial(platformId) {
            this.selectSocialPlatform(platformId);
        },
        removeSocial(index) {
            this.bioPage.social_links.splice(index, 1);
        },
        get filteredPlatforms() {
            const search = (this.socialSearch || '').toLowerCase();
            if (!this.platforms) return {};
            if (!search) return this.platforms;
            return Object.fromEntries(
                Object.entries(this.platforms).filter(([key, val]) => 
                    (val.name && val.name.toLowerCase().includes(search)) || key.includes(search)
                )
            );
        },
        uploadAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;
            // Validate file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                this.showNotification('Avatar must be less than 2MB', 'error');
                event.target.value = '';
                return;
            }
            // Validate file type
            if (!file.type.startsWith('image/')) {
                this.showNotification('Please select an image file', 'error');
                event.target.value = '';
                return;
            }
            const formData = new FormData();
            formData.append('avatar', file);
            this.showNotification('Uploading avatar...', 'info');
            fetch(bioEditorUrl('/upload-avatar'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(data => {
                        throw new Error(data.message || 'Upload failed');
                    });
                }
                return res.json();
            })
            .then(data => {
                if (data.avatar_url) {
                    // Store just the path without /storage/ prefix for consistency
                    const avatarPath = data.avatar_url.replace('/storage/', '');
                    this.bioPage.avatar_url = avatarPath;
                    this.showNotification('Avatar uploaded!', 'success');
                } else if (data.success === false) {
                    throw new Error(data.message || 'Upload failed');
                }
            })
            .catch(err => {
                console.error('Avatar upload error:', err);
                this.showNotification('Failed to upload: ' + err.message, 'error');
            })
            .finally(() => {
                event.target.value = '';
            });
        },
        uploadBackgroundImage(event) {
            const file = event.target.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('image', file);
            formData.append('_token', '{{ csrf_token() }}');
            fetch(bioEditorUrl('/upload-image'), {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.url) {
                    this.bioPage.background_image = data.path || data.image_url;
                    this.bioPage.background_value = data.url;
                    this.bioPage.background_type = 'image';
                    this.showNotification('Background uploaded!', 'success');
                }
            })
            .catch(() => this.showNotification('Failed to upload', 'error'));
        },
        applyTheme(themeId) {
            this.bioPage.theme = themeId;
            const theme = this.themes[themeId];
            if (theme) {
                const isGradient = theme.background.includes('gradient');
                this.bioPage.background_type = isGradient ? 'gradient' : 'solid';
                if (isGradient) {
                    this.bioPage.background_value = theme.background;
                    this.bioPage.background_gradient = theme.background;
                } else {
                    this.bioPage.background_value = theme.background;
                    this.bioPage.background_color = theme.background;
                }
                this.bioPage.title_color = theme.text;
                this.bioPage.bio_color = theme.bio;
                this.bioPage.link_bg_color = theme.link_bg;
                this.bioPage.button_bg_color = theme.link_bg;
                this.bioPage.link_text_color = theme.link_text || theme.text;
                this.bioPage.button_text_color = theme.link_text || theme.text;
                this.resetBlockColors();
            }
        },
        // Clear per-block button colours so the theme applies (live blocks, not the stale page copy).
        resetBlockColors() {
            this.blocks.forEach(block => {
                if (block.type === 'link' && (block.btn_bg_color || block.btn_text_color || block.btn_border_color)) {
                    block.btn_bg_color = null;
                    block.btn_text_color = null;
                    block.btn_border_color = null;
                    this.markBlockDirty(block.id);
                }
            });
        },
        /**
         * Copy style from a theme to the Default theme (custom mode)
         * @param {string} themeId Source theme ID
         * @param {string} what What to copy: 'button', 'background', or 'all'
         */
        copyThemeStyle(themeId, what) {
            const theme = this.themes[themeId];
            if (!theme) {
                this.showNotification('Theme not found', 'error');
                return;
            }
            // Switch to Default theme first
            this.bioPage.theme = 'default';
            if (what === 'button' || what === 'all') {
                // Copy button colors
                this.bioPage.link_bg_color = theme.link_bg;
                this.bioPage.button_bg_color = theme.link_bg;
                this.bioPage.link_text_color = theme.link_text || theme.text;
                this.bioPage.button_text_color = theme.link_text || theme.text;
                // Also copy text colors for consistency
                this.bioPage.title_color = theme.text;
                this.bioPage.bio_color = theme.bio;
                this.resetBlockColors();
            }
            if (what === 'background' || what === 'all') {
                // Copy background
                const isGradient = theme.background.includes('gradient');
                this.bioPage.background_type = isGradient ? 'gradient' : 'solid';
                if (isGradient) {
                    this.bioPage.background_value = theme.background;
                    this.bioPage.background_gradient = theme.background;
                } else {
                    this.bioPage.background_value = theme.background;
                    this.bioPage.background_color = theme.background;
                }
            }
            const whatLabel = what === 'button' ? 'Button style' : what === 'background' ? 'Background' : 'Style';
            this.showNotification(`${whatLabel} copied from ${theme.name}!`, 'success');
        },
        // Deletes blocks removed in the editor. Failed ids stay queued for the next save (M3).
        async processDeletions() {
            if (this.deletedBlockIds.length === 0) return 0;
            const results = await Promise.all(this.deletedBlockIds.map(id =>
                this.deleteBlockFromServer(id).then(() => null, () => id)
            ));
            this.deletedBlockIds = results.filter(id => id !== null);
            return this.deletedBlockIds.length;
        },
        // Countdown targets are saved as an exact moment (UTC), not a wall-clock time (M13).
        toLocalInput(value) {
            const d = value ? new Date(value) : null;
            if (!d || isNaN(d)) return '';
            const p = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
        },
        fromLocalInput(value) {
            const d = value ? new Date(value) : null;
            return d && !isNaN(d) ? d.toISOString() : '';
        },
        noteRevision(updatedAt) {
            if (updatedAt && (!this._baseUpdatedAt || new Date(updatedAt) >= new Date(this._baseUpdatedAt))) {
                this._baseUpdatedAt = updatedAt;
            }
        },
        async saveBioPage() {
            // One save at a time: a second click while saving would create new blocks twice (M2).
            if (this._saveInFlight) {
                this._saveQueued = true;
                return;
            }
            this._saveInFlight = true;
            this.saving = true;
            try {
                const { links, user, created_at, updated_at, ...bioData } = this.bioPage;
                bioData.social_links = (bioData.social_links || []).map(({ _uid, ...social }) => social);
                if (bioData.socials && !Array.isArray(bioData.socials)) {
                    delete bioData.socials;
                }
                bioData.base_updated_at = this._baseUpdatedAt;
                const response = await fetch(bioEditorUrl(''), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(bioData)
                });
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    console.error('Save error:', errorData);
                    throw new Error(errorData.message || 'Failed to save');
                }
                const saved = await response.json().catch(() => ({}));
                this.noteRevision(saved.bioPage?.updated_at);
                if (saved.bioPage?.slug && saved.bioPage.slug !== window.bioEditorSlug) {
                    window.bioEditorSlug = saved.bioPage.slug;
                    this.previewUrl = bioEditorUrl('/preview');
                }
                const failedDeletes = await this.processDeletions();
                await this.saveBlocks();
                if (failedDeletes > 0) {
                    throw new Error(`${failedDeletes} deleted block(s) could not be removed. Save again to retry.`);
                }
                this.showNotification('Saved successfully!', 'success');
                this.originalDataHash = this.getDataHash();
                this.hasUnsavedChanges = false;
            } catch (error) {
                console.error('Save failed:', error);
                this.showNotification('Failed to save: ' + error.message, 'error');
            } finally {
                this.saving = false;
                this._saveInFlight = false;
                if (this._saveQueued) {
                    this._saveQueued = false;
                    this.saveBioPage();
                }
            }
        },
        async saveBlocks() {
            // Collect all save promises for parallel execution
            const savePromises = [];
            for (const block of this.blocks) {
                if (block.id >= 1000000000) {
                    // New block - create
                    savePromises.push(this.createBlock(block));
                } else if (this.dirtyBlockIds.has(block.id)) {
                    // Existing block that was modified - update
                    savePromises.push(this.updateBlock(block));
                }
            }
            const created = this.blocks.some(b => b.id >= 1000000000);
            // Execute all saves in parallel
            if (savePromises.length > 0) {
                await Promise.all(savePromises);
            }
            // New blocks got real ids: persist the full order once.
            if ((created || this._orderDirty) && !(await this.saveBlocksOrder())) {
                throw new Error('The block order could not be saved');
            }
            // Clear dirty tracking after save
            this.dirtyBlockIds = new Set();
        },
        markBlockDirty(blockId) {
            if (blockId && blockId < 1000000000) {
                this.dirtyBlockIds.add(blockId);
            }
        },
        async createBlock(block) {
            const response = await fetch(bioEditorUrl('/links'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(block)
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok) {
                block.id = data.block?.id || data.id;
                this.noteRevision(data.page_updated_at);
            } else {
                throw new Error(data.message || 'Failed to create block');
            }
        },
        async updateBlock(block) {
            const response = await fetch(bioEditorUrl(`/links/${block.id}`), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(block)
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Failed to update block');
            }
            this.noteRevision(data.page_updated_at);
        },
        async deleteBlockFromServer(blockId) {
            const response = await fetch(bioEditorUrl(`/links/${blockId}`), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            });
            // 404: already gone (deleted from another tab), nothing left to retry.
            if (!response.ok && response.status !== 404) {
                throw new Error('Failed to delete block');
            }
            this.noteRevision((await response.json().catch(() => ({}))).page_updated_at);
        },
        async saveBlocksOrder() {
            const linksData = this.blocks
                .filter(b => b.id < 1000000000)
                .map((b, idx) => ({ id: b.id, order: idx }));
            if (linksData.length === 0) return true;
            try {
                const response = await fetch(bioEditorUrl('/links/reorder'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ links: linksData })
                });
                if (!response.ok) throw new Error(response.status);
                this.noteRevision((await response.json().catch(() => ({}))).page_updated_at);
                this._orderDirty = false;
                return true;
            } catch (e) {
                // Autosave of the order must not fail silently (M4): keep the change pending.
                console.error('Reorder failed:', e);
                this._orderDirty = true;
                this.hasUnsavedChanges = true;
                this.originalDataHash = null;
                this.showNotification('Could not save the new order. Click Save to retry.', 'error');
                return false;
            }
        },
        getFontFamily(fontValue) {
            const fontMap = {
                'inter': "'Inter', system-ui, sans-serif",
                'poppins': "'Poppins', system-ui, sans-serif",
                'roboto': "'Roboto', system-ui, sans-serif",
                'montserrat': "'Montserrat', system-ui, sans-serif",
                'nunito': "'Nunito', system-ui, sans-serif",
                'open-sans': "'Open Sans', system-ui, sans-serif",
                'lato': "'Lato', system-ui, sans-serif",
                'raleway': "'Raleway', system-ui, sans-serif",
                'quicksand': "'Quicksand', system-ui, sans-serif",
                'dm-sans': "'DM Sans', system-ui, sans-serif",
                'manrope': "'Manrope', system-ui, sans-serif",
                'plus-jakarta-sans': "'Plus Jakarta Sans', system-ui, sans-serif",
                'lexend': "'Lexend', system-ui, sans-serif",
                'space-grotesk': "'Space Grotesk', system-ui, sans-serif",
                'work-sans': "'Work Sans', system-ui, sans-serif",
                'oswald': "'Oswald', system-ui, sans-serif",
                'playfair-display': "'Playfair Display', Georgia, serif",
                'merriweather': "'Merriweather', Georgia, serif"
            };
            return fontMap[fontValue] || "'Inter', system-ui, sans-serif";
        },
        getFontDisplayName(fontValue) {
            const nameMap = {
                'inter': 'Inter',
                'poppins': 'Poppins',
                'roboto': 'Roboto',
                'montserrat': 'Montserrat',
                'nunito': 'Nunito',
                'open-sans': 'Open Sans',
                'lato': 'Lato',
                'raleway': 'Raleway',
                'quicksand': 'Quicksand',
                'dm-sans': 'DM Sans',
                'manrope': 'Manrope',
                'plus-jakarta-sans': 'Plus Jakarta Sans',
                'lexend': 'Lexend',
                'space-grotesk': 'Space Grotesk',
                'work-sans': 'Work Sans',
                'oswald': 'Oswald',
                'playfair-display': 'Playfair Display',
                'merriweather': 'Merriweather'
            };
            return nameMap[fontValue] || 'Inter';
        },
        getPlatformIcon(brandId) {
            if (!brandId) return '/images/brands/generic-website.svg?v=20251212';
            const brand = this.brands[brandId];
            return brand?.icon ? `/images/brands/${brand.icon}?v=20251212` : `/images/brands/${brandId}.svg?v=20251212`;
        },
        getPlatformColor(brandId) {
            return this.brands[brandId]?.color || '#6366f1';
        },
        // Get current active theme object
        getCurrentTheme() {
            const themeName = this.bioPage.theme || 'default';
            return this.themes[themeName] || this.themes['default'];
        },
        // Get effective button background color (respects theme)
        getEffectiveButtonBg() {
            const theme = this.getCurrentTheme();
            if (this.bioPage.link_bg_color) {
                return this.bioPage.link_bg_color;
            }
            return theme?.link_bg || '#6366f1';
        },
        // Get effective button text color (respects theme)
        getEffectiveButtonText() {
            const theme = this.getCurrentTheme();
            if (this.bioPage.link_text_color) {
                return this.bioPage.link_text_color;
            }
            return theme?.link_text || '#ffffff';
        },
        // Check if block is using auto style (no custom colors set)
        isBlockAutoStyle(block) {
            if (!block) return true;
            return !block.btn_bg_color && !block.btn_text_color && !block.btn_border_color && !block.btn_icon_invert;
        },
        // Toggle auto style for a block
        toggleBlockAutoStyle(block, isAuto) {
            if (isAuto) {
                // Reset all custom styles to follow theme
                block.btn_bg_color = null;
                block.btn_text_color = null;
                block.btn_border_color = null;
                block.btn_icon_invert = false;
            } else {
                // Set initial values based on current theme
                block.btn_bg_color = this.getEffectiveButtonBg();
                block.btn_text_color = this.getEffectiveButtonText();
                block.btn_border_color = null;
                block.btn_icon_invert = false;
            }
            this.markBlockDirty(block.id);
        },
        // Reset block to theme default
        resetBlockToTheme(block) {
            block.btn_bg_color = null;
            block.btn_text_color = null;
            block.btn_border_color = null;
            block.btn_icon_invert = false;
            this.markBlockDirty(block.id);
        },
        // Get auto icon filter based on theme button color
        getAutoIconFilter() {
            const bgColor = this.getEffectiveButtonBg();
            const isLight = this.isLightColor(bgColor);
            return isLight ? 'brightness(0)' : 'brightness(0) invert(1)';
        },
        getSocialPlaceholder(platform) {
            const placeholders = {
                'whatsapp': '+1 234 567 8900',
                'telegram': '@username or phone',
                'twitter': '@username',
                'x': '@username',
                'instagram': '@username',
                'facebook': 'facebook.com/yourpage',
                'youtube': 'youtube.com/@channel',
                'tiktok': '@username',
                'linkedin': 'linkedin.com/in/yourprofile',
                'github': 'github.com/username',
                'email': 'your@email.com',
                'website': 'https://yourwebsite.com',
                'discord': 'discord.gg/invite',
                'spotify': 'open.spotify.com/artist/...',
                'threads': '@username'
            };
            return placeholders[platform] || 'Enter your URL or handle';
        },
        validateSocialInput(index) {
            const social = this.bioPage.social_links[index];
            if (!social || !social.value) return;
            let value = social.value.trim();
            const atPrefixPlatforms = ['instagram', 'twitter', 'x', 'tiktok', 'threads'];
            if (atPrefixPlatforms.includes(social.platform) && !value.startsWith('@') && !value.includes('/')) {
                value = '@' + value;
            }
            this.bioPage.social_links[index].value = value;
        },
        getBlockTypeColor(type) {
            const colors = {
                'link': '#3b82f6',
                'text': '#10b981',
                'image': '#ec4899',
                'divider': '#6b7280',
                'spacer': '#6b7280',
                'header': '#8b5cf6'
            };
            return colors[type] || '#6366f1';
        },
        getBlockDisplayTitle(block) {
            if (!block) return 'Untitled';
            if (block.type === 'text' && block.content) {
                const temp = document.createElement('div');
                temp.innerHTML = block.content;
                const text = temp.textContent || temp.innerText || '';
                return text.substring(0, 50) + (text.length > 50 ? '...' : '') || 'Text Block';
            }
            if (block.type === 'image') {
                return block.title || 'Image';
            }
            if (block.type === 'divider') {
                return 'Divider';
            }
            if (block.type === 'header') {
                return block.title || 'Header';
            }
            return block.title || block.content || 'Untitled';
        },
        getBlockTypeIcon(type) {
            const icons = {
                'link': '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>',
                'text': '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>',
                'image': '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
                'divider': '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>',
                'spacer': '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>',
                'header': '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>'
            };
            return icons[type] || icons['link'];
        },
        addQuickBlock(type) {
            this.showAddBlockModal = false;
            if (type === 'text') {
                this.textEditorBlock = {
                    id: Date.now(),
                    type: 'text',
                    title: '',
                    url: '',
                    content: '',
                    is_active: true,
                    order: this.blocks.length
                };
                this.showTextEditor = true;
                this.$nextTick(() => {
                    if (this.$refs.richTextEditor) {
                        this.$refs.richTextEditor.innerHTML = '';
                        this.$refs.richTextEditor.focus();
                    }
                });
            } else if (type === 'image') {
                this.showImageUploader = true;
                this.imageUploadTab = 'upload';
                this.imageBlockUrl = '';
                this.imageBlockAlt = '';
                this.imageBlockLink = '';
            } else {
                this.addBlock(type);
            }
        },
        openBlockEditor(block) {
            if (block.type === 'text') {
                this.textEditorBlock = JSON.parse(JSON.stringify(block));
                this.showTextEditor = true;
                this.$nextTick(() => {
                    if (this.$refs.richTextEditor) {
                        this.$refs.richTextEditor.innerHTML = block.content || '';
                    }
                });
            } else {
                this.linkInputMode = 'create';
                this.editingBlock = JSON.parse(JSON.stringify(block));
            }
        },
        removeBlock(index) {
            const block = this.blocks[index];
            this.blocks.splice(index, 1);
            this.blocks.forEach((b, i) => b.order = i);
            if (block.id && block.id < 1000000000) {
                this.deletedBlockIds.push(block.id);
                this.checkForChanges();
            }
        },
        openTextEditor(block) {
            this.textEditorBlock = JSON.parse(JSON.stringify(block));
            this.showTextEditor = true;
            this.$nextTick(() => {
                if (this.$refs.richTextEditor) {
                    this.$refs.richTextEditor.innerHTML = this.textEditorBlock?.content || '';
                }
            });
        },
        formatText(command) {
            document.execCommand(command, false, null);
            if (this.$refs.richTextEditor) {
                this.$refs.richTextEditor.focus();
            }
        },
        updateTextBlockContent(event) {
            if (this.textEditorBlock) {
                this.textEditorBlock.content = event.target.innerHTML;
            }
        },
        saveTextBlock() {
            if (this.textEditorBlock) {
                const index = this.blocks.findIndex(b => b.id === this.textEditorBlock.id);
                if (index !== -1) {
                    this.blocks[index] = { ...this.textEditorBlock };
                } else {
                    this.blocks.push(this.textEditorBlock);
                }
                if (this.editingBlock && this.editingBlock.id === this.textEditorBlock.id) {
                    this.editingBlock.content = this.textEditorBlock.content;
                }
                this.markBlockDirty(this.textEditorBlock.id);
            }
            this.showTextEditor = false;
            this.textEditorBlock = null;
        },
        async uploadBlockImage(event, block) {
            const file = event.target.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                this.showNotification('Please select an image file', 'error');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                this.showNotification('Image size must be less than 5MB', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('image', file);
            formData.append('_token', '{{ csrf_token() }}');
            this.saving = true;
            try {
                const response = await fetch(bioEditorUrl('/upload-image'), {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.image_url) {
                    block.content = block.thumbnail_url = data.image_url.startsWith('http') || data.image_url.startsWith('/') ? data.image_url : '/storage/' + data.image_url;
                    this.markBlockDirty(block.id);
                    this.showNotification('Image uploaded!', 'success');
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            } catch(e) {
                console.error('Upload error:', e);
                this.showNotification('Failed to upload image', 'error');
            } finally {
                this.saving = false;
            }
        },
        async uploadImageBlock(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.saving = true;
            const formData = new FormData();
            formData.append('image', file);
            formData.append('_token', '{{ csrf_token() }}');
            try {
                const response = await fetch(bioEditorUrl('/upload-image'), {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.image_url) {
                    const imageUrl = data.image_url.startsWith('/') ? data.image_url : '/storage/' + data.image_url;
                    const newBlock = {
                        id: Date.now(),
                        type: 'image',
                        title: file.name || '',
                        url: '',
                        content: imageUrl,
                        thumbnail_url: imageUrl,
                        is_active: true,
                        order: this.blocks.length
                    };
                    this.blocks.push(newBlock);
                    this.showImageUploader = false;
                    this.imageBlockUrl = '';
                    this.imageBlockAlt = '';
                    this.showNotification('Image uploaded!', 'success');
                } else {
                    throw new Error('No image URL returned');
                }
            } catch (error) {
                console.error('Image upload error:', error);
                this.showNotification('Failed to upload image', 'error');
            } finally {
                this.saving = false;
            }
        },
        saveImageBlock() {
            if (this.imageBlockUrl) {
                const newBlock = {
                    id: Date.now(),
                    type: 'image',
                    title: this.imageBlockAlt || '',
                    url: '',
                    content: this.imageBlockUrl,
                    thumbnail_url: this.imageBlockUrl,
                    is_active: true,
                    order: this.blocks.length
                };
                this.blocks.push(newBlock);
                this.showImageUploader = false;
                this.imageBlockUrl = '';
                this.imageBlockAlt = '';
                this.showNotification('Image added!', 'success');
            } else {
                this.showNotification('Please enter an image URL', 'error');
            }
        },
        openIconPicker(block = null) {
            this.iconPickerBlock = block;
            this.showIconPicker = true;
            this.iconSearch = '';
        },
        openIconPickerSafe(block) {
            if (!block) {
                console.error('No block provided to icon picker');
                return;
            }
            this.iconPickerBlock = block;
            this.showIconPicker = true;
            this.iconSearch = '';
        },
        setBlockIcon(brandId) {
            const targetBlock = this.iconPickerBlock || this.editingBlock;
            if (targetBlock) {
                targetBlock.brand = brandId;
                if (!targetBlock.title) {
                    targetBlock.title = this.brands[brandId]?.name || brandId;
                }
                if (this.iconPickerBlock) {
                    const index = this.blocks.findIndex(b => b.id === this.iconPickerBlock.id);
                    if (index !== -1) {
                        this.blocks[index].brand = brandId;
                        if (!this.blocks[index].title) {
                            this.blocks[index].title = this.brands[brandId]?.name || brandId;
                        }
                    }
                }
            }
            this.showIconPicker = false;
            this.iconPickerBlock = null;
        },
        async uploadCustomIcon(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (file.size > 1024 * 1024) {
                this.showNotification('Icon must be less than 1MB', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('icon', file);
            try {
                const response = await fetch(bioEditorUrl('/upload-icon'), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: formData
                });
                const data = await response.json();
                if (data.success && data.icon_url) {
                    const targetBlock = this.iconPickerBlock || this.editingBlock;
                    if (targetBlock) {
                        targetBlock.custom_icon = data.icon_url;
                        targetBlock.brand = null;
                        if (this.iconPickerBlock) {
                            const index = this.blocks.findIndex(b => b.id === this.iconPickerBlock.id);
                            if (index !== -1) {
                                this.blocks[index].custom_icon = data.icon_url;
                                this.blocks[index].brand = null;
                            }
                        }
                    }
                    this.showNotification('Icon uploaded!', 'success');
                    this.showIconPicker = false;
                    this.iconPickerBlock = null;
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            } catch (error) {
                this.showNotification('Failed to upload icon', 'error');
                console.error(error);
            }
            event.target.value = '';
        },
        async fetchUrlTitle(block) {
            // Debounced: the editor may already be closed (block is null)
            if (!block || !block.url || (block.title && block.title.trim() !== '')) return;
            let url = block.url.trim();
            // Basic URL validation/normalization
            if (!url.match(/^https?:\/\//i)) {
                url = 'https://' + url;
            }
            try {
                const response = await fetch('/api/utils/fetch-title', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ url: url })
                });
                if (response.ok) {
                    const data = await response.json();
                    // Check again if title is still empty before setting it
                    if (data.title && (!block.title || block.title.trim() === '')) {
                        block.title = data.title;
                    }
                }
            } catch (e) {
                console.error('Error fetching title:', e);
            }
        },
        // Expands handles/phone numbers into full URLs. Runs on blur and before
        // saving - never while typing, so the field doesn't change under the cursor.
        processUrl(block, immediate = false) {
            if (this.urlInputTimeout) clearTimeout(this.urlInputTimeout);
            const run = () => {
                if (!block) return;
                let url = (block.url || '').trim();
                if (!url) return;
                // EARLY CHECK: Detect email pattern first - before any other processing
                // Matches: user@domain.com, test.user+tag@example.co.uk, etc.
                const emailPattern = /^[\w.+\-]+@[\w.\-]+\.[a-z]{2,}$/i;
                const isEmail = emailPattern.test(url.replace(/^mailto:/i, ''));
                // If it looks like an email (with or without mailto:), handle it specially
                if (isEmail || url.toLowerCase().startsWith('mailto:')) {
                    const cleanEmail = url.replace(/^mailto:/i, '').trim();
                    if (emailPattern.test(cleanEmail)) {
                        block.url = 'mailto:' + cleanEmail;
                    }
                    return; // Don't process further - this is an email
                }
                // Handle tel: prefix for phone numbers
                if (url.toLowerCase().startsWith('tel:')) {
                    return; // Already has tel: prefix, don't modify
                }
                // Handle sms: prefix for SMS
                if (url.toLowerCase().startsWith('sms:')) {
                    return; // Already has sms: prefix, don't modify
                }
                // Handle http:// (without s) - keep as is
                if (url.toLowerCase().startsWith('http://')) {
                    return; // Keep http:// as is, don't force https
                }
                const brandId = block.brand;
                const brand = this.brands[brandId];
                const usernamePatterns = {
                    'instagram': 'https://instagram.com/',
                    'x': 'https://x.com/',
                    'threads': 'https://threads.net/@',
                    'tiktok': 'https://tiktok.com/@',
                    'snapchat': 'https://snapchat.com/add/',
                    'telegram': 'https://t.me/',
                    'line': 'https://line.me/ti/p/',
                    'bluesky': 'https://bsky.app/profile/',
                };
                const subdomainPatterns = {
                    'tumblr': '.tumblr.com',
                    'bandcamp': '.bandcamp.com',
                };
                const pathPatterns = {
                    'facebook': 'https://facebook.com/',
                    'youtube': 'https://youtube.com/@',
                    'linkedin': 'https://linkedin.com/in/',
                    'pinterest': 'https://pinterest.com/',
                    'reddit': 'https://reddit.com/u/',
                    'twitch': 'https://twitch.tv/',
                    'github': 'https://github.com/',
                    'gitlab': 'https://gitlab.com/',
                    'spotify': 'https://open.spotify.com/',
                    'soundcloud': 'https://soundcloud.com/',
                    'discord': 'https://discord.gg/',
                    'messenger': 'https://m.me/',
                };
                if (url.match(/^https?:\/\//i)) {
                    return;
                }
                if (url.match(/^[a-z]+:/i)) {
                    return;
                }
                if (url.startsWith('@')) {
                    const username = url.substring(1);
                    if (brandId && usernamePatterns[brandId]) {
                        block.url = usernamePatterns[brandId] + username;
                        return;
                    }
                    return;
                }
                if (brandId === 'whatsapp') {
                    if (url.match(/^\+[\d\s\-()]+$/)) {
                        const phone = url.replace(/[\s\-()]/g, '').replace(/^\+/, '');
                        block.url = 'https://wa.me/' + phone;
                        return;
                    }
                    if (url.match(/^https?:\/\/(wa\.me|api\.whatsapp\.com)/i)) {
                        return;
                    }
                    return;
                }
                if (brandId === 'signal') {
                    if (url.match(/^\+[\d\s\-()]+$/)) {
                        const phone = url.replace(/[\s\-()]/g, '').replace(/^\+/, '');
                        block.url = 'https://signal.me/#p/+' + phone;
                        return;
                    }
                    return;
                }
                if (brandId && subdomainPatterns[brandId]) {
                    if (!url.includes('.')) {
                        block.url = 'https://' + url + subdomainPatterns[brandId];
                        return;
                    }
                }
                if (brandId && pathPatterns[brandId]) {
                    if (!url.includes('.') && !url.includes('/')) {
                        block.url = pathPatterns[brandId] + url;
                        return;
                    }
                }
                if (brandId === 'reddit') {
                    if (url.startsWith('u/') || url.startsWith('r/')) {
                        block.url = 'https://reddit.com/' + url;
                        return;
                    }
                }
                if (brandId === 'discord') {
                    if (!url.includes('.') && !url.includes('/')) {
                        block.url = 'https://discord.gg/' + url;
                        return;
                    }
                }
                // Phone and SMS - handle phone numbers with proper prefixes
                if (brandId === 'phone') {
                    if (url.match(/^\+?[\d\s\-()]+$/)) {
                        const phone = url.replace(/[\s\-()]/g, '');
                        block.url = 'tel:' + phone;
                        return;
                    }
                    return;
                }
                if (brandId === 'sms') {
                    if (url.match(/^\+?[\d\s\-()]+$/)) {
                        const phone = url.replace(/[\s\-()]/g, '');
                        block.url = 'sms:' + phone;
                        return;
                    }
                    return;
                }
                if (brandId === 'email') {
                    // Already handled above in early check
                    return;
                }
                if (url.includes('.')) {
                    block.url = 'https://' + url;
                }
            };
            if (immediate) {
                run();
            } else {
                this.urlInputTimeout = setTimeout(run, 1000);
            }
        },
        validateBrandUrl(block) {
            if (!block.brand || !block.url) return true;
            const brandDomains = {
                // Social Media
                'facebook': ['facebook.com', 'fb.com', 'fb.me'],
                'instagram': ['instagram.com', 'instagr.am'],
                'x': ['x.com', 'twitter.com'],
                'threads': ['threads.net'],
                'tiktok': ['tiktok.com', 'vm.tiktok.com'],
                'youtube': ['youtube.com', 'youtu.be', 'm.youtube.com'],
                'youtube-music': ['music.youtube.com'],
                'linkedin': ['linkedin.com'],
                'pinterest': ['pinterest.com', 'pin.it'],
                'snapchat': ['snapchat.com'],
                'reddit': ['reddit.com'],
                'tumblr': ['tumblr.com'],
                'bluesky': ['bsky.app'],
                'mastodon': [], // Allows any Mastodon instance
                // Messaging
                'whatsapp': ['wa.me', 'api.whatsapp.com'],
                'telegram': ['t.me', 'telegram.me'],
                'messenger': ['m.me', 'messenger.com'],
                'discord': ['discord.gg', 'discord.com'],
                'signal': ['signal.me'],
                'line': ['line.me'],
                'slack': ['slack.com'],
                // Music & Audio
                'spotify': ['open.spotify.com', 'spotify.com'],
                'apple-music': ['music.apple.com'],
                'apple-podcasts': ['podcasts.apple.com'],
                'amazon-music': ['music.amazon.com', 'amazon.com/music'],
                'soundcloud': ['soundcloud.com'],
                'bandcamp': ['bandcamp.com'],
                'deezer': ['deezer.com'],
                'tidal': ['tidal.com', 'listen.tidal.com'],
                'audiomack': ['audiomack.com'],
                // Video & Streaming
                'twitch': ['twitch.tv'],
                'vimeo': ['vimeo.com'],
                'dailymotion': ['dailymotion.com'],
                'kick': ['kick.com'],
                'rumble': ['rumble.com'],
                // Development
                'github': ['github.com'],
                'gitlab': ['gitlab.com'],
                'bitbucket': ['bitbucket.org'],
                'codepen': ['codepen.io'],
                'codesandbox': ['codesandbox.io'],
                'replit': ['replit.com'],
                'stackoverflow': ['stackoverflow.com'],
                'dev': ['dev.to'],
                'hashnode': ['hashnode.dev', 'hashnode.com'],
                // Design & Creative
                'dribbble': ['dribbble.com'],
                'behance': ['behance.net'],
                'figma': ['figma.com'],
                'artstation': ['artstation.com'],
                'deviantart': ['deviantart.com'],
                // Business & Productivity
                'notion': ['notion.so', 'notion.site'],
                'trello': ['trello.com'],
                'medium': ['medium.com'],
                'substack': ['substack.com'],
                'producthunt': ['producthunt.com'],
                'xing': ['xing.com'],
                // Gaming
                'steam': ['steamcommunity.com', 'store.steampowered.com'],
                'playstation': ['playstation.com', 'psnprofiles.com'],
                'xbox': ['xbox.com', 'xboxgamertag.com'],
                'nintendo': ['nintendo.com'],
                'epic-games': ['epicgames.com', 'store.epicgames.com'],
                'gog': ['gog.com'],
                'itch-io': ['itch.io'],
                'osu': ['osu.ppy.sh'],
                'vrchat': ['vrchat.com'],
                'roblox': ['roblox.com'],
                'battlenet': ['battle.net', 'blizzard.com'],
                // Payments & Finance
                'paypal': ['paypal.com', 'paypal.me'],
                'venmo': ['venmo.com'],
                'cashapp': ['cash.app'],
                'ko-fi': ['ko-fi.com'],
                'patreon': ['patreon.com'],
                'buymeacoffee': ['buymeacoffee.com'],
                // Shopping & E-commerce
                'amazon': ['amazon.com', 'amazon.co.uk', 'amazon.de', 'amazon.fr', 'amazon.ca', 'amazon.es', 'amazon.it'],
                'ebay': ['ebay.com', 'ebay.co.uk', 'ebay.de'],
                'etsy': ['etsy.com'],
                'shopify': ['myshopify.com'],
                // App Stores
                'appstore': ['apps.apple.com'],
                'playstore': ['play.google.com'],
                // Web3 & NFT
                'opensea': ['opensea.io'],
                'rarible': ['rarible.com'],
                'foundation': ['foundation.app'],
                // Extended platforms
                'clubhouse': ['clubhouse.com'],
                'guilded': ['guilded.gg'],
                'cameo': ['cameo.com'],
                'ngl': ['ngl.link'],
                'researchgate': ['researchgate.net'],
                'orcid': ['orcid.org'],
            };
            const domains = brandDomains[block.brand];
            if (!domains || domains.length === 0) return true;
            try {
                const urlObj = new URL(block.url);
                const hostname = urlObj.hostname.replace('www.', '');
                return domains.some(d => hostname === d || hostname.endsWith('.' + d));
            } catch (e) {
                return false;
            }
        },
        validatePlatformUrl(block) {
            if (!block) return true;
            if (!block.brand || !block.url) {
                block._urlError = null;
                return true;
            }
            const url = (block.url || '').trim();
            if (!url) {
                block._urlError = null;
                return true;
            }
            const brandId = block.brand;
            const brand = this.brands[brandId];
            const validationRules = {
                'signal': {
                    pattern: /^(\+\d{10,15}|https?:\/\/signal\.me\/#p\/\+\d+)$/,
                    message: 'Phone number with country code required (e.g., +1234567890)',
                    example: '+1234567890'
                },
                'whatsapp': {
                    pattern: /^(\+\d{10,15}|https?:\/\/(wa\.me|api\.whatsapp\.com)\/\d+.*)$/,
                    message: 'Phone with country code required (e.g., +1234567890) or wa.me link',
                    example: '+1234567890'
                },
                'instagram': {
                    pattern: /^(@?[\w.]+|https?:\/\/(www\.)?instagram\.com\/[\w.]+\/?.*$)/i,
                    message: 'Enter Instagram username or URL (e.g., @username or instagram.com/username)',
                    example: '@username'
                },
                'x': {
                    pattern: /^(@?[\w]+|https?:\/\/(www\.)?(x|twitter)\.com\/[\w]+\/?.*$)/i,
                    message: 'Enter X/Twitter username or URL (e.g., @username or x.com/username)',
                    example: '@username'
                },
                'twitter': {
                    pattern: /^(@?[\w]+|https?:\/\/(www\.)?(x|twitter)\.com\/[\w]+\/?.*$)/i,
                    message: 'Enter X/Twitter username or URL',
                    example: '@username'
                },
                'tiktok': {
                    pattern: /^(@?[\w.]+|https?:\/\/(www\.)?tiktok\.com\/@?[\w.]+\/?.*$)/i,
                    message: 'Enter TikTok username or URL (e.g., @username or tiktok.com/@username)',
                    example: '@username'
                },
                'telegram': {
                    pattern: /^(@?[\w]+|https?:\/\/(t\.me|telegram\.me)\/[\w]+\/?.*$)/i,
                    message: 'Enter Telegram username or URL (e.g., @username or t.me/username)',
                    example: 't.me/username'
                },
                'line': {
                    pattern: /^(@[\w\-]+|https?:\/\/line\.me\/.+)$/i,
                    message: 'Enter LINE ID (e.g., @lineid)',
                    example: '@lineid'
                },
                'messenger': {
                    pattern: /^([\w.]+|https?:\/\/(m\.me|messenger\.com)\/.+)$/i,
                    message: 'Enter Messenger username or m.me link',
                    example: 'm.me/username'
                },
                'discord': {
                    pattern: /^([\w]+|https?:\/\/(discord\.gg|discord\.com\/(invite)?)\/.+)$/i,
                    message: 'Enter Discord invite code or discord.gg link',
                    example: 'discord.gg/invitecode'
                },
                'threads': {
                    pattern: /^(@?[\w.]+|https?:\/\/(www\.)?threads\.net\/@?[\w.]+\/?.*$)/i,
                    message: 'Enter Threads username or URL (e.g., @username)',
                    example: '@username'
                },
                'youtube': {
                    pattern: /^https?:\/\/(www\.)?(youtube\.com|youtu\.be|m\.youtube\.com)\/.+$/i,
                    message: 'YouTube URL: youtube.com/@channel, youtu.be/VIDEO, or m.youtube.com/...',
                    example: 'youtube.com/@channel or youtu.be/videoID'
                },
                'spotify': {
                    pattern: /^https?:\/\/(open\.)?spotify\.com\/.+$/i,
                    message: 'Enter a valid Spotify URL (open.spotify.com/...)',
                    example: 'open.spotify.com/track/...'
                },
                'soundcloud': {
                    pattern: /^https?:\/\/(www\.)?soundcloud\.com\/.+$/i,
                    message: 'Enter a valid SoundCloud URL (soundcloud.com/...)',
                    example: 'soundcloud.com/username'
                },
                'github': {
                    pattern: /^https?:\/\/(www\.)?github\.com\/[\w\-]+\/?.*$/i,
                    message: 'Enter a valid GitHub URL (github.com/username)',
                    example: 'github.com/username'
                },
                'linkedin': {
                    pattern: /^https?:\/\/(www\.)?linkedin\.com\/(in|company)\/[\w\-]+\/?.*$/i,
                    message: 'Enter a valid LinkedIn URL (linkedin.com/in/profile)',
                    example: 'linkedin.com/in/profile'
                },
                'facebook': {
                    pattern: /^https?:\/\/(www\.)?(facebook|fb)\.(com|me)\/.+$/i,
                    message: 'Enter a valid Facebook URL (facebook.com/...)',
                    example: 'facebook.com/username'
                },
                'twitch': {
                    pattern: /^https?:\/\/(www\.)?twitch\.tv\/[\w_]+\/?.*$/i,
                    message: 'Enter a valid Twitch URL (twitch.tv/username)',
                    example: 'twitch.tv/username'
                },
                'pinterest': {
                    pattern: /^https?:\/\/(www\.)?pinterest\.com\/.+$/i,
                    message: 'Enter a valid Pinterest URL (pinterest.com/...)',
                    example: 'pinterest.com/username'
                },
                'reddit': {
                    pattern: /^https?:\/\/(www\.)?reddit\.com\/(u|r|user)\/.+$/i,
                    message: 'Enter a valid Reddit URL (reddit.com/u/... or reddit.com/r/...)',
                    example: 'reddit.com/u/username'
                },
                'patreon': {
                    pattern: /^https?:\/\/(www\.)?patreon\.com\/[\w\-]+\/?.*$/i,
                    message: 'Enter a valid Patreon URL (patreon.com/username)',
                    example: 'patreon.com/username'
                },
                'ko-fi': {
                    pattern: /^https?:\/\/(www\.)?ko-fi\.com\/[\w]+\/?.*$/i,
                    message: 'Enter a valid Ko-fi URL (ko-fi.com/username)',
                    example: 'ko-fi.com/username'
                },
                'buy-me-a-coffee': {
                    pattern: /^https?:\/\/(www\.)?buymeacoffee\.com\/[\w]+\/?.*$/i,
                    message: 'Enter a valid Buy Me a Coffee URL (buymeacoffee.com/username)',
                    example: 'buymeacoffee.com/username'
                },
                'paypal': {
                    pattern: /^https?:\/\/(www\.)?paypal\.(me|com)\/[\w]+\/?.*$/i,
                    message: 'Enter a valid PayPal URL (paypal.me/username)',
                    example: 'paypal.me/username'
                },
                'venmo': {
                    pattern: /^(@?[\w]+|https?:\/\/(www\.)?venmo\.com\/[\w]+.*)$/i,
                    message: 'Enter Venmo username (e.g., @username)',
                    example: '@username'
                },
                'cash-app': {
                    pattern: /^(\$[\w]+|https?:\/\/(www\.)?cash\.app\/\$[\w]+.*)$/i,
                    message: 'Enter Cash App cashtag (e.g., $cashtag)',
                    example: '$cashtag'
                },
                'snapchat': {
                    pattern: /^(@?[\w]+|https?:\/\/(www\.)?snapchat\.com\/add\/[\w]+.*)$/i,
                    message: 'Enter Snapchat username (e.g., @username)',
                    example: '@username'
                },
                'bluesky': {
                    pattern: /^(@?[\w.-]+\.bsky\.social|@?[\w.-]+|https?:\/\/bsky\.app\/profile\/.+)$/i,
                    message: 'Enter Bluesky handle (e.g., @handle.bsky.social)',
                    example: '@handle.bsky.social'
                },
                'mastodon': {
                    pattern: /^(@?[\w]+@[\w.-]+|https?:\/\/.+\/@?[\w]+.*)$/i,
                    message: 'Enter Mastodon handle (e.g., @user@instance.social)',
                    example: '@user@instance.social'
                },
                'behance': {
                    pattern: /^https?:\/\/(www\.)?behance\.net\/[\w]+\/?.*$/i,
                    message: 'Enter a valid Behance URL (behance.net/username)',
                    example: 'behance.net/username'
                },
                'dribbble': {
                    pattern: /^https?:\/\/(www\.)?dribbble\.com\/[\w]+\/?.*$/i,
                    message: 'Enter a valid Dribbble URL (dribbble.com/username)',
                    example: 'dribbble.com/username'
                },
                'medium': {
                    pattern: /^https?:\/\/(www\.)?medium\.com\/@?[\w]+\/?.*$/i,
                    message: 'Enter a valid Medium URL (medium.com/@username)',
                    example: 'medium.com/@username'
                },
                'figma': {
                    pattern: /^https?:\/\/(www\.)?figma\.com\/@?[\w]+\/?.*$/i,
                    message: 'Enter a valid Figma URL (figma.com/@username)',
                    example: 'figma.com/@username'
                },
                'notion': {
                    pattern: /^https?:\/\/(www\.)?(notion\.so|notion\.site)\/.+$/i,
                    message: 'Enter a valid Notion URL (notion.so/...)',
                    example: 'notion.so/...'
                },
                'calendly': {
                    pattern: /^https?:\/\/(www\.)?calendly\.com\/[\w\-]+\/?.*$/i,
                    message: 'Enter a valid Calendly URL (calendly.com/username)',
                    example: 'calendly.com/username'
                },
                'vimeo': {
                    pattern: /^https?:\/\/(www\.)?vimeo\.com\/.+$/i,
                    message: 'Enter a valid Vimeo URL (vimeo.com/...)',
                    example: 'vimeo.com/username'
                },
                'apple-music': {
                    pattern: /^https?:\/\/music\.apple\.com\/.+$/i,
                    message: 'Enter a valid Apple Music URL (music.apple.com/...)',
                    example: 'music.apple.com/...'
                },
                'apple-podcasts': {
                    pattern: /^https?:\/\/podcasts\.apple\.com\/.+$/i,
                    message: 'Enter a valid Apple Podcasts URL (podcasts.apple.com/...)',
                    example: 'podcasts.apple.com/...'
                },
                'youtube-music': {
                    pattern: /^https?:\/\/music\.youtube\.com\/.+$/i,
                    message: 'Enter a valid YouTube Music URL (music.youtube.com/...)',
                    example: 'music.youtube.com/...'
                },
                'amazon-music': {
                    pattern: /^https?:\/\/music\.amazon\.(com|co\.\w+)\/.+$/i,
                    message: 'Enter a valid Amazon Music URL (music.amazon.com/...)',
                    example: 'music.amazon.com/...'
                },
                'bandcamp': {
                    pattern: /^https?:\/\/[\w\-]+\.bandcamp\.com\/?.*$/i,
                    message: 'Enter a valid Bandcamp URL (username.bandcamp.com)',
                    example: 'username.bandcamp.com'
                },
                'tumblr': {
                    pattern: /^https?:\/\/[\w\-]+\.tumblr\.com\/?.*$/i,
                    message: 'Enter a valid Tumblr URL (username.tumblr.com)',
                    example: 'username.tumblr.com'
                },
                'substack': {
                    pattern: /^https?:\/\/[\w\-]+\.substack\.com\/?.*$/i,
                    message: 'Enter a valid Substack URL (username.substack.com)',
                    example: 'username.substack.com'
                },
                'steam': {
                    pattern: /^https?:\/\/(www\.)?(steamcommunity\.com\/(id|profiles)|store\.steampowered\.com\/(app|sub)|steampowered\.com)\/.+$/i,
                    message: 'Enter a valid Steam URL (steamcommunity.com/id/... or store.steampowered.com/app/...)',
                    example: 'steamcommunity.com/id/username or store.steampowered.com/app/...'
                },
                'etsy': {
                    pattern: /^https?:\/\/(www\.)?etsy\.com\/shop\/.+$/i,
                    message: 'Enter a valid Etsy shop URL (etsy.com/shop/...)',
                    example: 'etsy.com/shop/yourshop'
                },
                'amazon': {
                    pattern: /^https?:\/\/(www\.)?amazon\.(com|co\.\w+)\/.+$/i,
                    message: 'Enter a valid Amazon URL (amazon.com/...)',
                    example: 'amazon.com/...'
                },
                'gumroad': {
                    pattern: /^https?:\/\/(www\.)?gumroad\.com\/[\w]+\/?.*$/i,
                    message: 'Enter a valid Gumroad URL (gumroad.com/username)',
                    example: 'gumroad.com/username'
                },
                'letterboxd': {
                    pattern: /^https?:\/\/(www\.)?letterboxd\.com\/[\w]+\/?.*$/i,
                    message: 'Enter a valid Letterboxd URL (letterboxd.com/username)',
                    example: 'letterboxd.com/username'
                },
                'goodreads': {
                    pattern: /^https?:\/\/(www\.)?goodreads\.com\/(user|author)\/.+$/i,
                    message: 'Enter a valid Goodreads URL (goodreads.com/user/...)',
                    example: 'goodreads.com/user/...'
                },
                'unsplash': {
                    pattern: /^https?:\/\/(www\.)?unsplash\.com\/@[\w\-]+\/?.*$/i,
                    message: 'Enter a valid Unsplash URL (unsplash.com/@username)',
                    example: 'unsplash.com/@username'
                },
                'slack': {
                    pattern: /^https?:\/\/[\w\-]+\.slack\.com\/?.*$/i,
                    message: 'Enter a valid Slack URL (workspace.slack.com)',
                    example: 'workspace.slack.com'
                },
                'kick': {
                    pattern: /^https?:\/\/(www\.)?kick\.com\/[\w]+\/?.*$/i,
                    message: 'Enter a valid Kick URL (kick.com/username)',
                    example: 'kick.com/username'
                },
                'gitlab': {
                    pattern: /^https?:\/\/(www\.)?gitlab\.com\/[\w\-]+\/?.*$/i,
                    message: 'Enter a valid GitLab URL (gitlab.com/username)',
                    example: 'gitlab.com/username'
                },
                'dev-to': {
                    pattern: /^https?:\/\/(www\.)?dev\.to\/[\w]+\/?.*$/i,
                    message: 'Enter a valid DEV.to URL (dev.to/username)',
                    example: 'dev.to/username'
                },
                'stack-overflow': {
                    pattern: /^https?:\/\/(www\.)?stackoverflow\.com\/(users|questions)\/.+$/i,
                    message: 'Enter a valid Stack Overflow URL (stackoverflow.com/users/...)',
                    example: 'stackoverflow.com/users/...'
                },
                'hashnode': {
                    pattern: /^https?:\/\/(www\.)?hashnode\.com\/@[\w]+\/?.*$/i,
                    message: 'Enter a valid Hashnode URL (hashnode.com/@username)',
                    example: 'hashnode.com/@username'
                },
                'artstation': {
                    pattern: /^https?:\/\/(www\.)?artstation\.com\/[\w]+\/?.*$/i,
                    message: 'Enter a valid ArtStation URL (artstation.com/username)',
                    example: 'artstation.com/username'
                },
                'vsco': {
                    pattern: /^https?:\/\/(www\.)?vsco\.co\/[\w]+\/?.*$/i,
                    message: 'Enter a valid VSCO URL (vsco.co/username)',
                    example: 'vsco.co/username'
                },
                'flickr': {
                    pattern: /^https?:\/\/(www\.)?flickr\.com\/photos\/[\w\-]+\/?.*$/i,
                    message: 'Enter a valid Flickr URL (flickr.com/photos/username)',
                    example: 'flickr.com/photos/username'
                },
                'fiverr': {
                    pattern: /^https?:\/\/(www\.)?fiverr\.com\/[\w]+\/?.*$/i,
                    message: 'Enter a valid Fiverr URL (fiverr.com/username)',
                    example: 'fiverr.com/username'
                },
                'trello': {
                    pattern: /^https?:\/\/(www\.)?trello\.com\/b\/.+$/i,
                    message: 'Enter a valid Trello URL (trello.com/b/...)',
                    example: 'trello.com/b/...'
                },
                'gofundme': {
                    pattern: /^https?:\/\/(www\.)?gofundme\.com\/f\/.+$/i,
                    message: 'Enter a valid GoFundMe URL (gofundme.com/f/...)',
                    example: 'gofundme.com/f/...'
                },
                'kickstarter': {
                    pattern: /^https?:\/\/(www\.)?kickstarter\.com\/projects\/.+$/i,
                    message: 'Enter a valid Kickstarter URL (kickstarter.com/projects/...)',
                    example: 'kickstarter.com/projects/...'
                },
                'tidal': {
                    pattern: /^https?:\/\/(www\.)?(tidal\.com|listen\.tidal\.com)\/.+$/i,
                    message: 'Enter a valid TIDAL URL (tidal.com/browse/... or listen.tidal.com/...)',
                    example: 'tidal.com/browse/artist/...'
                },
                'deezer': {
                    pattern: /^https?:\/\/(www\.)?deezer\.com\/.+$/i,
                    message: 'Enter a valid Deezer URL (deezer.com/...)',
                    example: 'deezer.com/artist/...'
                },
                'xing': {
                    pattern: /^https?:\/\/(www\.)?xing\.com\/profile\/[\w\-]+\/?.*$/i,
                    message: 'Enter a valid XING URL (xing.com/profile/username)',
                    example: 'xing.com/profile/username'
                },
                'email': {
                    pattern: /^(mailto:)?[\w.+-]+@[\w.-]+\.\w{2,}$/i,
                    message: 'Enter a valid email address',
                    example: 'your@email.com'
                },
                'phone': {
                    pattern: /^\+\d{10,15}$/,
                    message: 'Enter phone with country code (e.g., +1234567890)',
                    example: '+1234567890'
                },
                'sms': {
                    pattern: /^\+\d{10,15}$/,
                    message: 'Enter phone with country code (e.g., +1234567890)',
                    example: '+1234567890'
                },
            };
            const rule = validationRules[brandId];
            if (!rule) {
                block._urlError = null;
                return true;
            }
            if (!rule.pattern.test(url)) {
                block._urlError = rule.message;
                return false;
            }
            block._urlError = null;
            return true;
        },
        copyPageUrl() {
            navigator.clipboard.writeText('{{ url("/") }}/' + this.bioPage.slug)
                .then(() => this.showNotification('URL copied!', 'success'));
        },
        /**
         * Get Simple Icons CDN URL for a platform
         * @param {string} platform Platform identifier
         * @param {string|null} color Optional hex color without # (e.g., 'ffffff' for white)
         * @returns {string|null} CDN URL or null if not available
         */
        getSimpleIconUrl(platform, color = null) {
            const mapping = {
                'amazon': 'amazon',
                'amazon-music': 'amazonmusic',
                'apple': 'apple',
                'apple-music': 'applemusic',
                'apple-music-alt': 'applemusic',
                'apple-podcasts': 'applepodcasts',
                'apple-podcasts-alt': 'applepodcasts',
                'artstation': 'artstation',
                'audiomack': 'audiomack',
                'bandcamp': 'bandcamp',
                'behance': 'behance',
                'bereal': 'bereal',
                'bluesky': 'bluesky',
                'bluesky-alt': 'bluesky',
                'buy-me-a-coffee': 'buymeacoffee',
                'cal': 'caldotcom',
                'calendly': 'calendly',
                'cameo': 'cameo',
                'cash-app': 'cashapp',
                'cash-app-btc': 'cashapp',
                'cash-app-dollar': 'cashapp',
                'cash-app-pound': 'cashapp',
                'clubhouse': 'clubhouse',
                'codeberg': 'codeberg',
                'codepen': 'codepen',
                'deezer': 'deezer',
                'dev-to': 'devdotto',
                'discord': 'discord',
                'discogs': 'discogs',
                'discogs-alt': 'discogs',
                'dribbble': 'dribbble',
                'email': 'gmail',
                'email-alt': 'maildotru',
                'etsy': 'etsy',
                'facebook': 'facebook',
                'figma': 'figma',
                'fiverr': 'fiverr',
                'flickr': 'flickr',
                'github': 'github',
                'gitlab': 'gitlab',
                'gofundme': 'gofundme',
                'gog': 'gogdotcom',
                'goodreads': 'goodreads',
                'google-drive': 'googledrive',
                'google-play': 'googleplay',
                'google-podcasts': 'googlepodcasts',
                'google-scholar': 'googlescholar',
                'guilded': 'guilded',
                'gumroad': 'gumroad',
                'hackerrank': 'hackerrank',
                'hashnode': 'hashnode',
                'instagram': 'instagram',
                'itch-io': 'itchdotio',
                'keybase': 'keybase',
                'kick': 'kick',
                'kick-alt': 'kick',
                'kickstarter': 'kickstarter',
                'kit': 'convertkit',
                'ko-fi': 'kofi',
                'last-fm': 'lastdotfm',
                'leetcode': 'leetcode',
                'lemmy': 'lemmy',
                'letterboxd': 'letterboxd',
                'line': 'line',
                'linkedin': 'linkedin',
                'mailchimp': 'mailchimp',
                'mastodon': 'mastodon',
                'matrix': 'matrix',
                'medium': 'medium',
                'meetup': 'meetup',
                'meetup-alt': 'meetup',
                'messenger': 'messenger',
                'microsoft': 'microsoft',
                'mixcloud': 'mixcloud',
                'myanimelist': 'myanimelist',
                'nostr': 'nostr',
                'notion': 'notion',
                'obsidian': 'obsidian',
                'onlyfans': 'onlyfans',
                'opensea': 'opensea',
                'orcid': 'orcid',
                'osu': 'osu',
                'patreon': 'patreon',
                'paypal': 'paypal',
                'pinterest': 'pinterest',
                'pixelfed': 'pixelfed',
                'playstation': 'playstation',
                'product-hunt': 'producthunt',
                'qobuz': 'qobuz',
                'redbubble': 'redbubble',
                'reddit': 'reddit',
                'researchgate': 'researchgate',
                'revolut': 'revolut',
                'signal': 'signal',
                'signal-alt': 'signal',
                'simplex': 'simplex',
                'slack': 'slack',
                'snapchat': 'snapchat',
                'soundcloud': 'soundcloud',
                'spotify': 'spotify',
                'spotify-alt': 'spotify',
                'stack-overflow': 'stackoverflow',
                'steam': 'steam',
                'strava': 'strava',
                'substack': 'substack',
                'telegram': 'telegram',
                'threads': 'threads',
                'threema': 'threema',
                'tidal': 'tidal',
                'tiktok': 'tiktok',
                'trakt': 'trakt',
                'trello': 'trello',
                'tumblr': 'tumblr',
                'twitch': 'twitch',
                'twitter': 'twitter',
                'unsplash': 'unsplash',
                'upwork': 'upwork',
                'venmo': 'venmo',
                'vimeo': 'vimeo',
                'vrchat': 'vrchat',
                'vsco': 'vsco',
                'whatsapp': 'whatsapp',
                'wordpress': 'wordpress',
                'x': 'x',
                'xbox': 'xbox',
                'xing': 'xing',
                'youtube': 'youtube',
                'youtube-alt': 'youtube',
                'youtube-music': 'youtubemusic',
                'zoom': 'zoom',
            };
            const iconName = mapping[platform];
            if (!iconName) return null;
            let url = `https://cdn.simpleicons.org/${iconName}`;
            if (color) {
                url += `/${color.replace('#', '')}`;
            }
            return url;
        },
        /**
         * Get icon URL - tries Simple Icons first, falls back to local
         * @param {string} platform Platform identifier
         * @param {string|null} color Optional color for Simple Icons
         * @returns {string} Icon URL
         */
        getIconUrl(platform, color = null) {
            // Try Simple Icons first
            const simpleUrl = this.getSimpleIconUrl(platform, color);
            if (simpleUrl) return simpleUrl;
            // Fall back to local icons
            return `/images/brands/${platform}.svg?v=20251212`;
        },
        /**
         * Auto-color module: Calculate optimal text color based on background
         * Uses WCAG luminance calculation for accessibility
         */
        getContrastColor(bgColor) {
            if (!bgColor) return '#ffffff';
            // Handle hex colors
            let hex = bgColor;
            if (hex.startsWith('#')) {
                hex = hex.slice(1);
            }
            // Handle shorthand hex
            if (hex.length === 3) {
                hex = hex.split('').map(c => c + c).join('');
            }
            // Handle rgba/rgb
            if (bgColor.startsWith('rgb')) {
                const rgb = bgColor.match(/\d+/g);
                if (rgb && rgb.length >= 3) {
                    const r = parseInt(rgb[0]);
                    const g = parseInt(rgb[1]);
                    const b = parseInt(rgb[2]);
                    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                    return luminance > 0.5 ? '#000000' : '#ffffff';
                }
            }
            // Handle gradient - extract first color
            if (bgColor.includes('gradient')) {
                const colorMatch = bgColor.match(/#[a-fA-F0-9]{3,6}/);
                if (colorMatch) {
                    return this.getContrastColor(colorMatch[0]);
                }
                return '#ffffff';
            }
            // Parse hex
            const r = parseInt(hex.substr(0, 2), 16);
            const g = parseInt(hex.substr(2, 2), 16);
            const b = parseInt(hex.substr(4, 2), 16);
            // Calculate relative luminance
            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            return luminance > 0.5 ? '#000000' : '#ffffff';
        },
        /**
         * Get recommended colors for a background
         * Returns object with text, subtle, and accent colors
         */
        getRecommendedColors(bgColor) {
            const textColor = this.getContrastColor(bgColor);
            const isLight = textColor === '#000000';
            return {
                text: textColor,
                subtle: isLight ? '#4b5563' : '#9ca3af',
                accent: isLight ? '#3b82f6' : '#60a5fa',
                muted: isLight ? '#6b7280' : '#94a3b8',
                border: isLight ? 'rgba(0,0,0,0.1)' : 'rgba(255,255,255,0.1)'
            };
        },
        /**
         * Check if a color is light
         */
        isLightColor(color) {
            return this.getContrastColor(color) === '#000000';
        },
        /**
         * Auto-apply recommended text color when button background changes
         * Call this when user changes button_bg_color
         */
        autoApplyTextColor(block) {
            if (block.btn_bg_color) {
                block.btn_text_color = this.getContrastColor(block.btn_bg_color);
                this.markBlockDirty(block.id);
            }
        },
        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-[200] px-6 py-3 rounded-lg shadow-lg text-white ${type === 'success' ? 'bg-green-700' : type === 'error' ? 'bg-red-700' : 'bg-blue-700'}`;
            notification.setAttribute('role', type === 'error' ? 'alert' : 'status');
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        },
        confirmDelete() {
            if (confirm('Delete this page permanently?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = bioEditorUrl('');
                form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="DELETE">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    };
}
</script>
@endpush

