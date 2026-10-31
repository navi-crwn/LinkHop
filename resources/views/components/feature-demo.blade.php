@props(['name', 'label'])
<figure class="my-5 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-slate-950">
    <img src="{{ asset('images/demos/'.$name.'-light.webp') }}" alt="{{ __($label) }}" width="1120" height="760" loading="lazy" decoding="async" class="block h-auto w-full dark:hidden">
    <img src="{{ asset('images/demos/'.$name.'-dark.webp') }}" alt="{{ __($label) }}" width="1120" height="760" loading="lazy" decoding="async" class="hidden h-auto w-full dark:block">
</figure>
