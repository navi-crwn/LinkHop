{{-- The family theme before first paint: cookie hop_theme, then the old localStorage key ($oldKey), then the system
     (resources/js/theme-boot.js, inlined so it costs no request). Also defines window.hopSetTheme for the toggles. --}}
<script nonce="{{ Vite::cspNonce() }}" data-old-key="{{ $oldKey ?? 'helink-theme' }}">{!! file_get_contents(resource_path('js/theme-boot.js')) !!}</script>
