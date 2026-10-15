<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Link;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLinkController extends Controller
{
    public function index(Request $request)
    {
        $query = Link::query()->with(['user:id,name,email', 'folder:id,name', 'tags:id,name']);
        $status = $request->string('status')->toString();
        $userId = $request->integer('user_id');
        $folderId = $request->integer('folder_id');
        $tag = ltrim($request->string('tag')->trim()->toString(), '#');
        $sort = $request->string('sort')->toString();
        match ($status) {
            'active' => $query->live(),
            'inactive' => $query->where('status', Link::STATUS_INACTIVE),
            'expired' => $query->where('status', Link::STATUS_ACTIVE)->ended(),
            default => null,
        };
        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($request->string('owner')->toString() === 'guest') {
            $query->whereNull('user_id');
        }
        if ($userId && $folderId) {
            $query->where('folder_id', $folderId);
        }
        if ($tag !== '') {
            $query->whereHas('tags', fn ($q) => $q->where('tags.name', 'like', "%{$tag}%"));
        }
        $search = $request->string('q')->trim()->toString();
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%")
                    ->orWhere('target_url', 'like', "%{$search}%");
            });
        }
        $sort === 'clicks' ? $query->orderByDesc('clicks') : $query->latest('id');
        $links = $query->paginate(20)->withQueryString();
        // Only the selected owner's folders are offered, so the filter never lists every folder on the platform.
        $owner = $userId ? User::find($userId, ['id', 'name', 'email']) : null;

        return view('admin-links', [
            'links' => $links,
            'status' => $status,
            'search' => $search,
            'owner' => $owner,
            'folders' => $owner ? Folder::where('user_id', $owner->id)->orderBy('name')->get(['id', 'name']) : collect(),
            'filters' => [
                'user_id' => $userId,
                'folder_id' => $folderId,
                'tag' => $tag,
                'sort' => $sort,
                'guest' => $request->string('owner')->toString() === 'guest',
            ],
        ]);
    }

    public function my(Request $request)
    {
        $user = $request->user();
        $query = Link::query()
            ->where('user_id', $user->id)
            ->with(['folder', 'tags'])
            ->latest();
        // Support both 'q' and 'query' parameters
        $search = $request->string('q')->trim()->toString() ?: $request->string('query')->trim()->toString();
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%")
                    ->orWhere('target_url', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }
        $folderId = $request->integer('folder_id');
        if ($folderId) {
            $query->where('folder_id', $folderId);
        }
        $tagId = $request->integer('tag_id');
        if ($tagId) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }
        // Return JSON if requested
        if ($request->wantsJson() || $request->ajax()) {
            $links = $query->limit(20)->get()->map(function ($link) {
                return [
                    'id' => $link->id,
                    'title' => $link->title,
                    'slug' => $link->slug,
                    'url' => $link->target_url,
                    'short_url' => url($link->slug),
                    'clicks' => $link->clicks,
                ];
            });

            return response()->json($links);
        }
        $links = $query->paginate(15)->withQueryString();
        $folders = $user->folders;
        $tags = $user->tags;

        return view('admin-links-my', compact('links', 'search', 'folders', 'tags', 'folderId', 'tagId'));
    }

    public function updateStatus(Request $request, Link $link)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([Link::STATUS_ACTIVE, Link::STATUS_INACTIVE])],
        ]);
        $link->update(['status' => $validated['status']]);

        return back()->with('status', "Link {$link->slug} status updated.");
    }

    public function destroy(Link $link)
    {
        $link->delete();

        return back()->with('status', "Link {$link->slug} deleted.");
    }

    public function bulk(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'action' => ['required', 'in:disable,delete'],
        ]);
        // Per-model so the redirect cache is cleared (model events), not a mass query.
        $links = Link::whereIn('id', $request->input('ids'))->get();
        if ($request->action === 'delete') {
            $links->each->delete();
            $message = 'Links deleted.';
        } else {
            $links->each(fn (Link $link) => $link->update(['status' => Link::STATUS_INACTIVE]));
            $message = 'Links disabled.';
        }

        return back()->with('status', $message);
    }
}
