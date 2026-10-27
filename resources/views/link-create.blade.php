<x-app-layout>
    <x-slot name="pageTitle">LinkHop - New link</x-slot>
    <x-page-header title="New link" subtitle="Shorten a destination URL.">
        <x-slot name="actions">
            <a href="{{ route('links.index') }}" class="btn-ghost btn-sm">My links</a>
        </x-slot>
    </x-page-header>

    <x-flash />

    <form method="POST" action="{{ route('links.store') }}" class="glass-card mx-auto w-full max-w-2xl space-y-4">
        @csrf
        <input type="hidden" name="status" value="active">
        <div>
            <label for="flinkcr-1" class="label">Destination URL</label>
            <input id="flinkcr-1" type="url" name="target_url" value="{{ old('target_url') }}" required class="input" placeholder="https://">
        </div>
        <div>
            <label for="flinkcr-2" class="label">Custom slug (optional)</label>
            <div class="flex items-center gap-2">
                <span class="shrink-0 text-sm text-muted">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/</span>
                <input id="flinkcr-2" type="text" name="slug" value="{{ old('slug') }}" class="input" placeholder="custom-slug">
            </div>
        </div>
        @if ($folders->isNotEmpty())
            <div>
                <label for="flinkcr-3" class="label">Folder</label>
                <select id="flinkcr-3" name="folder_id" class="input">
                    @foreach ($folders as $folder)
                        <option value="{{ $folder->id }}" @selected((int) old('folder_id', request('folder', $folders->firstWhere('is_default', true)?->id)) === (int) $folder->id)>{{ $folder->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="flex justify-end gap-2">
            <a href="{{ route('links.index') }}" class="btn-ghost btn-sm">Cancel</a>
            <button type="submit" class="btn-primary btn-sm">Create link</button>
        </div>
    </form>
</x-app-layout>
