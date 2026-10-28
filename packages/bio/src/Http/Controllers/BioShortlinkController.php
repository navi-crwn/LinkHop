<?php

namespace Hop\Bio\Http\Controllers;

use App\Http\Controllers\Controller;
use Hop\Bio\Contracts\ShortlinkCreator;
use Hop\Bio\Contracts\UrlSafety;
use Illuminate\Http\Request;

/**
 * "Shorten this URL" and "pick one of my links" in the bio editor, through the
 * ShortlinkCreator contract the host app binds.
 */
class BioShortlinkController extends Controller
{
    public function __construct(private readonly ShortlinkCreator $shortlinks, private readonly UrlSafety $safety) {}

    public function search(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([], 200);
        }

        return response()->json($this->shortlinks->search($user, trim((string) $request->get('query', ''))));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);
        $url = self::normalizeUrl($data['url']);
        if ($reason = $this->safety->rejectionReason($url, (string) ($data['title'] ?? ''))) {
            return response()->json(['message' => $reason, 'errors' => ['url' => [$reason]]], 422);
        }
        try {
            $link = $this->shortlinks->findOrCreate($user, $url, $data['title'] ?? null);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Failed to create shortlink'], 500);
        }

        return response()->json($link, $link['reused'] ? 200 : 201);
    }

    /** Adds https:// when no scheme was typed. */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        return preg_match('/^[a-z][a-z0-9+.\-]*:/i', $url) ? $url : 'https://'.ltrim($url, '/');
    }
}
