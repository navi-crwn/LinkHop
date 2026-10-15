<x-app-layout>
    <x-slot name="pageTitle">HEL.ink - Edit /{{ $link->slug }}</x-slot>
    <x-page-header title="Edit link" subtitle="{{ $link->short_url }}">
        <x-slot name="actions">
            <a href="{{ route('links.show', $link) }}" class="btn-ghost btn-sm">Analytics</a>
            <a href="{{ route('links.index') }}" class="btn-soft btn-sm">My links</a>
        </x-slot>
    </x-page-header>

    <x-flash />

    @php
        $selectedTags = collect(old('tags', $link->tags->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
        $redirect = (string) old('redirect_type', $link->redirect_type);
    @endphp

    <form x-data="{ submitting: false }" x-on:submit="submitting = true" method="POST" action="{{ route('links.update', $link) }}"
          class="glass mx-auto w-full max-w-4xl divide-glass rounded-2xl">
        @csrf
        @method('PUT')

        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div>
                <h2 class="section-title">Destination</h2>
                <p class="mt-1 text-xs text-muted">Where the short link sends visitors.</p>
            </div>
            <div class="space-y-3 md:col-span-2">
                <div>
                    <label class="label" for="target_url">Destination URL</label>
                    <input id="target_url" type="url" name="target_url" value="{{ old('target_url', $link->target_url) }}" required class="input">
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="label" for="slug">Slug</label>
                        <div class="flex items-center gap-2">
                            <span class="shrink-0 text-sm text-muted">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/</span>
                            <input id="slug" type="text" name="slug" value="{{ old('slug', $link->slug) }}" class="input">
                        </div>
                    </div>
                    <div>
                        <label class="label" for="status">Status</label>
                        <select id="status" name="status" class="input">
                            <option value="active" @selected(old('status', $link->status) === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $link->status) === 'inactive')>Inactive</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label" for="redirect_type">Redirect type</label>
                    <select id="redirect_type" name="redirect_type" class="input sm:w-60!">
                        <option value="302" @selected($redirect === '302')>302 · temporary</option>
                        <option value="301" @selected($redirect === '301')>301 · permanent</option>
                        <option value="307" @selected($redirect === '307')>307 · temporary, keeps method</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div>
                <h2 class="section-title">Organize</h2>
                <p class="mt-1 text-xs text-muted">Folder and tags only you can see.</p>
            </div>
            <div class="space-y-3 md:col-span-2">
                <div>
                    <label class="label" for="folder_id">Folder</label>
                    <select id="folder_id" name="folder_id" class="input" required>
                        @foreach ($folders as $folder)
                            <option value="{{ $folder->id }}" @selected((int) old('folder_id', $link->folder_id) === (int) $folder->id)>{{ $folder->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-data="{ search: '' }">
                    <div class="flex items-center justify-between gap-2">
                        <span class="label">Tags</span>
                        @if (count($tags) > 12)
                            <input type="search" x-model="search" placeholder="Filter tags" aria-label="Filter tags" class="input w-40! py-1! text-xs">
                        @endif
                    </div>
                    @if (count($tags) === 0)
                        <p class="text-xs text-muted">No tags yet. <a href="{{ route('tags.index') }}" class="underline">Create one</a>.</p>
                    @else
                        <div class="flex max-h-36 flex-wrap gap-1.5 overflow-y-auto">
                            @foreach ($tags as $tag)
                                <label class="cursor-pointer" x-show="!search || @js(mb_strtolower($tag->name)).includes(search.toLowerCase())">
                                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="peer sr-only" @checked(in_array((int) $tag->id, $selectedTags, true))>
                                    <span class="inline-flex rounded-full border border-slate-500/20 px-2.5 py-1 text-xs text-muted transition peer-checked:border-indigo-500/50 peer-checked:bg-indigo-500/15 peer-checked:text-indigo-700 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500/40 dark:peer-checked:text-indigo-300">#{{ $tag->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div>
                <h2 class="section-title">Protection</h2>
                <p class="mt-1 text-xs text-muted">{{ $link->requiresPassword() ? 'A password is set.' : 'No password set.' }} {{ $link->expires_at ? 'Expires '.$link->expires_at->diffForHumans().'.' : '' }}</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 md:col-span-2">
                <div>
                    <label for="flinked-1" class="label">New password</label>
                    <input id="flinked-1" type="password" name="password" class="input" placeholder="Leave blank to keep" autocomplete="new-password">
                    @if ($link->requiresPassword())
                        <label class="mt-2 flex items-center gap-2 text-xs text-muted">
                            <input type="checkbox" name="remove_password" value="1" class="rounded-sm border-slate-400/50 text-indigo-600 focus:ring-indigo-500">
                            Remove the current password
                        </label>
                    @endif
                </div>
                <div>
                    <label for="flinked-2" class="label">Expires at</label>
                    <input id="flinked-2" type="datetime-local" name="expires_at" value="{{ old('expires_at', optional($link->expires_at)->format('Y-m-d\TH:i')) }}" class="input">
                </div>
            </div>
        </div>

        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div>
                <h2 class="section-title">Social preview</h2>
                <p class="mt-1 text-xs text-muted">Overrides the title, description and image shown when the link is shared.</p>
            </div>
            <div class="space-y-3 md:col-span-2">
                <div>
                    <label for="flinked-3" class="label">Title</label>
                    <input id="flinked-3" type="text" name="custom_title" value="{{ old('custom_title', $link->custom_title) }}" class="input">
                </div>
                <div>
                    <label for="flinked-5" class="label">Description</label>
                    <textarea id="flinked-5" name="custom_description" rows="2" class="input">{{ old('custom_description', $link->custom_description) }}</textarea>
                </div>
                <div>
                    <label for="flinked-6" class="label">Image URL</label>
                    <input id="flinked-6" type="url" name="custom_image_url" value="{{ old('custom_image_url', $link->custom_image_url) }}" class="input" placeholder="https://">
                </div>
            </div>
        </div>

        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div>
                <h2 class="section-title">Note</h2>
                <p class="mt-1 text-xs text-muted">Adds an internal note to the link's history.</p>
            </div>
            <div class="md:col-span-2">
                <label for="flinked-4" class="sr-only">New note</label>
                <textarea id="flinked-4" name="comment" rows="2" class="input" placeholder="Internal note"></textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 p-4">
            <a href="{{ route('links.index') }}" class="btn-ghost btn-sm">Cancel</a>
            <button type="submit" class="btn-primary btn-sm" :disabled="submitting">
                <span x-text="submitting ? 'Saving…' : 'Save changes'">Save changes</span>
            </button>
        </div>
    </form>
</x-app-layout>
