<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\LinkClick;
use App\Models\QrCode;
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

        $codes = QrCode::where('user_id', $user->id)->orderBy('created_at')->get()
            ->map(fn (QrCode $q) => ['type' => $q->type, 'created_at' => $q->created_at?->toIso8601String(), 'open' => $q->opened()]);

        return response()->json(['ok' => true, 'app' => 'linkhop', 'data' => [
            'links' => $links,
            'folders' => $user->folders()->orderBy('name')->get(['name', 'is_default', 'created_at']),
            'tags' => $user->tags()->orderBy('name')->get(['name', 'created_at']),
            // the member's own saved codes, with what they carry (the hub bundles this into the member's own download)
            'qr_codes' => $codes->filter(fn (array $c) => $c['open'] !== null)->map(fn (array $c) => [
                'type' => $c['type'], 'label' => $c['open']['label'], 'content' => $c['open']['payload'], 'created_at' => $c['created_at'],
            ])->values(),
            // saved codes that could not be opened (the app key changed): counted, not exported
            'qr_codes_unreadable' => $codes->filter(fn (array $c) => $c['open'] === null)->count(),
        ]]);
    }

    /** GET /internal/accounts/{user}/summary: a few counts for the hub dashboard card (numbers only, no content). */
    public function summary(User $user): JsonResponse
    {
        return response()->json(['ok' => true, 'app' => 'linkhop', 'data' => [
            'links' => $user->links()->count(),
            'clicks_7d' => LinkClick::whereIn('link_id', $user->links()->select('id'))->where('clicked_at', '>=', now()->subDays(7))->count(),
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
}
