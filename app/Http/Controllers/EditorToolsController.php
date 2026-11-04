<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\RetiredSlug;
use App\Support\ReservedSlugs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Small JSON endpoints for the dashboard's JavaScript: "is this slug free?" and the command palette's link search. */
class EditorToolsController extends Controller
{
    /**
     * GET /links/slug-check?slug=abc[&link=ID]: whether the form would accept this slug. It answers only with
     * available or not and why in general terms; it never says whose link holds a slug. `link` is the link being
     * edited, whose own slug counts as free.
     */
    public function slugCheck(Request $request): JsonResponse
    {
        $slug = Str::lower(trim(is_string($request->query('slug')) ? $request->query('slug') : ''));
        $current = null;
        if ($id = $request->integer('link')) {
            $link = Link::find($id);
            if ($link && ($link->user_id === $request->user()->id || $request->user()->isAdmin())) {
                $current = $link;
            }
        }
        [$reason, $message] = match (true) {
            $slug === '' => [null, null],
            $current && strcasecmp($slug, $current->slug) === 0 => [null, null],
            ! preg_match('/^[a-z0-9-]+$/', $slug) => ['format', __('Slug can only contain letters, numbers, and hyphens.')],
            strlen($slug) < 3 => ['short', __('The slug needs at least 3 characters.')],
            strlen($slug) > 48 => ['long', __('The slug can have 48 characters at most.')],
            ReservedSlugs::isReserved($slug) => ['reserved', __('This slug is reserved.')],
            Link::where('slug', $slug)->exists() || RetiredSlug::isBlocked($slug) => ['taken', __('This slug is already taken.')],
            default => [null, null],
        };
        $available = $slug !== '' && $reason === null;

        return response()->json([
            'available' => $available,
            'reason' => $reason,
            'message' => $reason ? $message : ($available ? __('This slug is available.') : null),
        ])->header('Cache-Control', 'no-store, private');
    }

    /** GET /palette/links?q=: up to 8 of the caller's own links, newest first when there is no query. */
    public function links(Request $request): JsonResponse
    {
        $q = Str::limit(trim(is_string($request->query('q')) ? $request->query('q') : ''), 80, '');
        $query = $request->user()->links()->latest()->orderByDesc('id')->limit(8);
        if ($q !== '') {
            // % and _ in the query are plain characters (an explicit escape works the same on MySQL and SQLite)
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $query->where(fn ($w) => $w->whereRaw("slug like ? escape '!'", [$like])->orWhereRaw("target_url like ? escape '!'", [$like])->orWhereRaw("custom_title like ? escape '!'", [$like]));
        }

        return response()->json([
            'links' => $query->get(['id', 'slug', 'target_url', 'custom_title', 'status', 'user_id'])->map(fn (Link $l) => [
                'id' => $l->id,
                'slug' => $l->slug,
                'title' => $l->custom_title,
                'target_url' => Str::limit($l->target_url, 90),
                'status' => $l->status,
                'url' => route('links.show', $l),
                'edit_url' => route('links.edit', $l),
            ])->all(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
