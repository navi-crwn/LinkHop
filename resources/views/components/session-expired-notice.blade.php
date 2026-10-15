{{-- Shown after a form was sent with an expired session token (see bootstrap/app.php). --}}
@if (session('session_expired'))
    <div role="alert" x-data="{ show: true }" x-show="show"
         class="fixed inset-x-0 top-4 z-[100] mx-auto flex w-[calc(100%-2rem)] max-w-md items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-lg dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
        <p class="flex-1">{{ session('session_expired') }}</p>
        <button type="button" x-on:click="show = false" class="font-semibold" aria-label="Close">&times;</button>
    </div>
@endif
