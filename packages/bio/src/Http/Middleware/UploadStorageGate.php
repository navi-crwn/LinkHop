<?php

namespace Hop\Bio\Http\Middleware;

use Closure;
use Hop\Bio\Support\UploadStorage;
use Illuminate\Http\Request;

class UploadStorageGate
{
    public function handle(Request $request, Closure $next)
    {
        $qr = $request->input('qr_settings', []);
        if (is_string($qr)) {
            $qr = json_decode($qr, true);
        }
        $hasUpload = $request->allFiles() !== [] || str_contains((string) $request->route()?->getName(), 'upload-')
            || (is_array($qr) && str_starts_with((string) ($qr['image'] ?? ''), 'data:image'));
        if ($request->isMethod('GET') || ! $hasUpload) {
            return $next($request);
        }
        // A filesystem lock serialises PHP workers sharing this local upload disk.
        $lock = @fopen(storage_path('framework/bio-upload.lock'), 'c');
        try {
            if (! $lock || ! flock($lock, LOCK_EX) || ! app(UploadStorage::class)->available(max(0, (int) $request->server('CONTENT_LENGTH')))) {
                return response()->json([
                    'success' => false,
                    'code' => 'storage_unavailable',
                    'message' => __('Uploads are temporarily paused because server storage is low or unavailable. Please try again later.'),
                ], 507, ['Retry-After' => '60']);
            }

            return $next($request);
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
