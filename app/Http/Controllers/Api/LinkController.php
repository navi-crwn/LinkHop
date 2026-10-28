<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Services\LinkService;
use GuzzleHttp\Client;
use Hop\Bio\Contracts\ShortlinkCreator;
use Hop\Safety\LinkSafety;
use Hop\Safety\UrlGuard;
use Illuminate\Http\Request;

class LinkController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([], 200);
        }
        $query = trim((string) $request->get('query', ''));
        $links = Link::query()
            ->where('user_id', $user->id)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('custom_title', 'like', "%{$query}%")
                        ->orWhere('target_url', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
        // Map to expected format
        $result = $links->map(function ($link) {
            return [
                'id' => $link->id,
                'title' => $link->custom_title ?: 'Untitled',
                'url' => $link->target_url,
                'short_url' => $link->short_url,
            ];
        });

        return response()->json($result);
    }

    public function shorten(Request $request, LinkService $linkService)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
        ]);
        $url = $this->normalizeUrl($data['url']);
        if ($reason = app(LinkSafety::class)->rejectionReason($url, (string) ($data['title'] ?? ''))) {
            return response()->json(['message' => $reason, 'errors' => ['url' => [$reason]]], 422);
        }
        // Use LinkService to create a shortlink for the user
        $link = $linkService->createShortlink(
            $user,
            $url,
            $data['title'] ?? 'Bio Link',
            (bool) ($data['is_public'] ?? false)
        );

        return response()->json([
            'id' => $link->id,
            'title' => $link->title,
            'url' => $link->url,
            'short_url' => $link->short_url,
        ], 201);
    }

    /**
     * Create a shortlink for a bio block (the hop/bio package's ShortlinkCreator door; kept for API clients).
     */
    public function shortenForBio(Request $request, ShortlinkCreator $shortlinks)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);
        $url = $this->normalizeUrl($data['url']);
        if ($reason = app(LinkSafety::class)->rejectionReason($url, (string) ($data['title'] ?? ''))) {
            return response()->json(['message' => $reason, 'errors' => ['url' => [$reason]]], 422);
        }
        try {
            $link = $shortlinks->findOrCreate($user, $url, $data['title'] ?? null);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['message' => 'Failed to create shortlink'], 500);
        }

        return response()->json($link, $link['reused'] ? 200 : 201);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (! preg_match('/^[a-z][a-z0-9+.\-]*:/i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        return $url;
    }

    public function fetchTitle(Request $request)
    {
        $request->validate(['url' => 'required|url']);
        $url = $request->input('url');
        if (! app(UrlGuard::class)->isSafe($url)) {
            return response()->json(['title' => null], 422);
        }
        try {
            $client = new Client(['timeout' => 5, 'verify' => true, 'allow_redirects' => false]);
            $response = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],
            ]);
            $html = (string) $response->getBody();
            if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
                return response()->json(['title' => html_entity_decode(trim($matches[1]))]);
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        return response()->json(['title' => null]);
    }
}
