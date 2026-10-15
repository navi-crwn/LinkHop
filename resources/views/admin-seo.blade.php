<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - SEO</x-slot>
    <x-page-header title="SEO" subtitle="Meta tags, social previews and site icons." />

    <x-flash />

    <form method="POST" action="{{ route('admin.seo.update') }}" class="grid items-start gap-3 lg:grid-cols-3"
        x-data="{
            siteTitle: @js(old('site_title', $seo->site_title ?? config('app.name'))),
            metaDescription: @js(old('meta_description', $seo->meta_description ?? 'A fast shortlink studio for campaigns, QR codes, and analytics.')),
            favicon: @js(old('favicon', $seo->favicon ?? '')),
            logo: @js(old('logo', $seo->logo ?? '')),
            ogTitle: @js(old('og_title', $seo->og_title ?? 'Share smarter with HEL.ink')),
            ogDescription: @js(old('og_description', $seo->og_description ?? 'Track clicks, protect links, manage QR codes, and keep abuse away.'))
        }">
        @csrf

        <div class="space-y-3 lg:col-span-2">
            <section class="glass-card space-y-3">
                <h2 class="section-title">Search engines</h2>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <div class="flex items-baseline justify-between">
                            <label for="fadmins-1" class="label">Site title</label>
                            <span class="text-[11px] tabular-nums" :class="siteTitle.length > 60 ? 'text-rose-500' : 'text-muted'" x-text="siteTitle.length + '/60'"></span>
                        </div>
                        <input id="fadmins-1" type="text" name="site_title" x-model="siteTitle" class="input">
                    </div>
                    <div>
                        <label for="fadmins-2" class="label">Keywords</label>
                        <input id="fadmins-2" type="text" name="meta_keywords" value="{{ old('meta_keywords', $seo->meta_keywords ?? '') }}" class="input" placeholder="shortlink, url shortener, hel.ink">
                    </div>
                </div>
                <div>
                    <div class="flex items-baseline justify-between">
                        <label for="fadmins-3" class="label">Meta description</label>
                        <span class="text-[11px] tabular-nums" :class="metaDescription.length > 160 ? 'text-rose-500' : 'text-muted'" x-text="metaDescription.length + '/160'"></span>
                    </div>
                    <textarea id="fadmins-3" name="meta_description" rows="3" x-model="metaDescription" class="input"></textarea>
                </div>
            </section>

            <section class="glass-card space-y-3">
                <h2 class="section-title">Social sharing</h2>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label for="fadmins-4" class="label">Open Graph title</label>
                        <input id="fadmins-4" type="text" name="og_title" x-model="ogTitle" class="input">
                    </div>
                    <div>
                        <label for="fadmins-5" class="label">Open Graph description</label>
                        <input id="fadmins-5" type="text" name="og_description" x-model="ogDescription" class="input">
                    </div>
                </div>
                <div>
                    <label for="fadmins-6" class="label">OG image URL</label>
                    <input id="fadmins-6" type="url" name="og_image" value="{{ old('og_image', $seo->og_image ?? '') }}" class="input" placeholder="https://">
                    <p class="mt-1 text-xs text-muted">1200×630 JPG or PNG.</p>
                </div>
            </section>

            <section class="glass-card space-y-3">
                <h2 class="section-title">Brand assets</h2>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label for="fadmins-7" class="label">Favicon URL</label>
                        <input id="fadmins-7" type="url" name="favicon" x-model="favicon" class="input" placeholder="https://">
                        <p class="mt-1 text-xs text-muted">Square PNG, 512×512 works best.</p>
                    </div>
                    <div>
                        <label for="fadmins-8" class="label">Logo URL</label>
                        <input id="fadmins-8" type="url" name="logo" x-model="logo" class="input" placeholder="https://">
                        <p class="mt-1 text-xs text-muted">Transparent PNG, at least 256×256.</p>
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary">Save SEO</button>
            </div>
        </div>

        <aside class="space-y-3 lg:sticky lg:top-24">
            <section class="glass-card space-y-2">
                <p class="eyebrow">Search preview</p>
                <div class="glass-inset rounded-xl p-3">
                    <div class="flex items-center gap-2 text-xs text-muted">
                        <img x-show="favicon" :src="favicon" alt="" class="h-4 w-4 rounded object-contain">
                        <span class="truncate">{{ url('/') }}</span>
                    </div>
                    <p class="mt-1 line-clamp-1 text-base font-semibold text-indigo-600 dark:text-indigo-300" x-text="siteTitle || 'HEL.ink'"></p>
                    <p class="line-clamp-2 text-xs text-muted" x-text="metaDescription || 'Add a description so search engines know what HEL.ink offers.'"></p>
                </div>
            </section>
            <section class="glass-card space-y-2">
                <p class="eyebrow">Social preview</p>
                <div class="glass-inset flex gap-3 rounded-xl p-3">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-500/10">
                        <img x-show="logo" :src="logo" alt="" class="h-full w-full object-contain">
                        <span x-show="!logo" class="text-[11px] text-muted">Logo</span>
                    </div>
                    <div class="min-w-0">
                        <p class="line-clamp-1 text-sm font-semibold text-ink" x-text="ogTitle || siteTitle"></p>
                        <p class="line-clamp-2 text-xs text-muted" x-text="ogDescription || metaDescription"></p>
                    </div>
                </div>
            </section>
        </aside>
    </form>
</x-app-layout>
