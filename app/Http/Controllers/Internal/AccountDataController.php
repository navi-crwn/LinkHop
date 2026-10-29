<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function purge(int $user, Request $request, UserDeletion $deletion): JsonResponse
    {
        $stats = $deletion->purgeById(
            $user,
            (array) $request->input('qr_codes', []),
            (array) $request->input('bio_uploads', []),
        );

        return response()->json(['ok' => true, 'app' => 'linkhop', 'data' => $stats]);
    }

    /** POST /hop/events: handle account lifecycle events from the hub. */
    public function events(Request $request, UserDeletion $deletion): JsonResponse
    {
        $type = (string) $request->input('type', '');
        $data = (array) $request->input('data', []);
        if ($type === 'account.deleted') {
            $userId = (int) ($data['sub'] ?? $data['hop_id'] ?? 0);
            $deletion->purgeById(
                $userId,
                (array) ($data['qr_codes'] ?? []),
                (array) ($data['bio_uploads'] ?? []),
            );
        }

        return response()->json(['ok' => true]);
    }
}
