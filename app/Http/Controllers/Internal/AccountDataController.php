<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserDeletion;
use Illuminate\Http\JsonResponse;

/**
 * What LinkHop holds for one account, for the hub (signed requests, see
 * VerifyHubRequest). The hub owns the account itself; this app only owns the
 * links, folders, tags and comments under it.
 */
class AccountDataController extends Controller
{
    /** GET /internal/accounts/{user}/export: links, folders and tags as JSON (the hub bundles every app's answer). */
    public function export(User $user): JsonResponse
    {
        $links = $user->links()->with(['folder:id,name', 'tags:id,name'])->withCount('clicks')->get()->map(fn ($l) => [
            'slug' => $l->slug,
            'short_url' => $l->short_url,
            'target_url' => $l->target_url,
            'status' => $l->status,
            'redirect_type' => $l->redirect_type,
            'folder' => $l->folder?->name,
            'tags' => $l->tags->pluck('name')->values(),
            'password_protected' => $l->requiresPassword(),
            'clicks' => $l->clicks_count,
            'created_at' => $l->created_at?->toIso8601String(),
            'expires_at' => $l->expires_at?->toIso8601String(),
        ]);

        return response()->json(['ok' => true, 'app' => 'linkhop', 'data' => [
            'links' => $links,
            'folders' => $user->folders()->orderBy('name')->get(['name', 'is_default', 'created_at']),
            'tags' => $user->tags()->orderBy('name')->get(['name', 'created_at']),
        ]]);
    }

    /** DELETE /internal/accounts/{user}: remove this app's rows and files for the account (the hub deletes the user). */
    public function purge(User $user, UserDeletion $deletion): JsonResponse
    {
        $stats = $deletion->purge($user);

        return response()->json(['ok' => true, 'app' => 'linkhop', 'data' => $stats]);
    }
}
