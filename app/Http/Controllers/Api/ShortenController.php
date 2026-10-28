<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HandlesSlugs;
use App\Models\ActivityLog;
use App\Models\Folder;
use App\Models\Link;
use App\Services\LinkService;
use App\Services\QuotaService;
use Hop\Safety\LinkSafety;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The token API (link.hel.ink/api): create, read and delete a short link.
 * `hop.token:{scope}` (hop/auth) has already resolved the token's owner as
 * the request user. Request and response shapes are documented in README, "API".
 */
class ShortenController extends Controller
{
    use HandlesSlugs;

    public function __construct(
        private LinkService $links
    ) {}

    /** GET /api/links/{slug}: one of the caller's links with its click count. */
    public function show(Request $request, string $slug): JsonResponse
    {
        $link = $this->ownLink($request, $slug);

        return response()->json(['success' => true, 'data' => $this->payload($link) + ['clicks' => $link->clicks()->count()]]);
    }

    /** DELETE /api/links/{slug}: remove one of the caller's links (and its QR code). */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $link = $this->ownLink($request, $slug);
        if ($link->qr_code_path) {
            Storage::disk('public')->delete($link->qr_code_path);
        }
        $link->tags()->detach();
        $link->comments()->delete();
        $link->delete();
        $this->links->forgetCache($link);
        ActivityLog::log('deleted', 'Link', $link->id, "Deleted link via API: {$link->slug}");

        return response()->json(['success' => true, 'message' => 'Link deleted']);
    }

    private function ownLink(Request $request, string $slug): Link
    {
        $link = Link::where('slug', $slug)->where('user_id', $request->user()->id)->first();
        if (! $link) {
            abort(response()->json(['success' => false, 'error' => 'Not Found', 'message' => 'No such link on this account'], 404));
        }

        return $link;
    }

    /** @return array<string, mixed> */
    private function payload(Link $link): array
    {
        return [
            'url' => $link->short_url,
            'slug' => $link->slug,
            'target_url' => $link->target_url,
            'status' => $link->status,
            'created_at' => $link->created_at->toIso8601String(),
            'expires_at' => $link->expires_at?->toIso8601String(),
        ];
    }

    /**
     * POST /api/shorten: create a short link (scope link:create). BioHop calls this for its blocks.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        // Validate the request
        $minSlugLength = config('shortener.min_custom_slug_length', 3);
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'alias' => "nullable|string|min:{$minSlugLength}|max:255|regex:/^[a-zA-Z0-9_-]+$/",
            'folder' => 'nullable|string',
            'expires_at' => 'nullable|date|after:now',
            'password' => 'nullable|string|min:4|max:50',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation Error',
                'messages' => $validator->errors(),
            ], 422);
        }
        $user = $request->user();
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'error' => 'Email Not Verified',
                'message' => 'Verify your email address before creating links.',
            ], 403);
        }
        $targetUrl = $request->input('url');
        // Add https:// if missing
        if (! preg_match('/^https?:\/\//i', $targetUrl)) {
            $targetUrl = 'https://'.$targetUrl;
        }
        if ($reason = app(LinkSafety::class)->rejectionReason($targetUrl, (string) $request->input('alias'))) {
            return response()->json([
                'success' => false,
                'error' => 'Validation Error',
                'messages' => ['url' => [$reason]],
            ], 422);
        }
        // Check user quota
        if (! app(QuotaService::class)->checkUserLimits($user->id)) {
            return response()->json([
                'success' => false,
                'error' => 'Quota Exceeded',
                'message' => 'Daily link quota reached. Please try again tomorrow.',
            ], 429);
        }
        try {
            // Prepare folder
            $folderId = null;
            if ($request->filled('folder')) {
                $folder = Folder::where('user_id', $user->id)
                    ->where('name', $request->input('folder'))
                    ->first();
                if (! $folder) {
                    // Create folder if not exists
                    $folder = Folder::create([
                        'user_id' => $user->id,
                        'name' => $request->input('folder'),
                        'is_default' => false,
                    ]);
                }
                $folderId = $folder->id;
            } else {
                // Use default folder
                $defaultFolder = Folder::where('user_id', $user->id)
                    ->where('is_default', true)
                    ->first();
                if ($defaultFolder) {
                    $folderId = $defaultFolder->id;
                }
            }
            // Prepare slug (alias)
            $slug = $this->prepareSlug($request->input('alias'));
            // Create the link
            $link = $user->links()->create([
                'target_url' => $targetUrl,
                'slug' => $slug,
                'status' => Link::STATUS_ACTIVE,
                'folder_id' => $folderId,
                'redirect_type' => '302',
                'expires_at' => $request->input('expires_at'),
                'password_hash' => $request->filled('password')
                    ? Hash::make($request->input('password'))
                    : null,
            ]);
            // Generate QR code
            $this->links->generateQr($link);
            $this->links->forgetCache($link);
            // Log activity
            ActivityLog::log(
                'created',
                'Link',
                $link->id,
                "Created link via API: {$link->slug} → {$link->target_url}"
            );

            // Return success response
            return response()->json([
                'success' => true,
                'data' => $this->payload($link),
                'message' => 'Link created successfully',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation Error',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('API link creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Server Error',
                'message' => 'An error occurred while creating the link',
            ], 500);
        }
    }
}
