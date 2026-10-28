<?php

namespace App\Bio;

use App\Models\Link;
use App\Services\LinkService;
use Hop\Bio\Contracts\ShortlinkCreator;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The hop/bio package's door to this app's shortener: one short link per
 * target URL and account, created without the daily quota (the route applies it).
 */
class ShortlinkCreatorAdapter implements ShortlinkCreator
{
    public function __construct(private readonly LinkService $links) {}

    public function findOrCreate(Authenticatable $user, string $url, ?string $title = null): array
    {
        $existing = Link::where('user_id', $user->getAuthIdentifier())->where('target_url', $url)->first();
        if ($existing) {
            return self::row($existing) + ['reused' => true];
        }
        $link = $this->links->createShortlink($user, $url, $title ?: 'Bio Link', false);

        return self::row($link) + ['reused' => false];
    }

    public function search(Authenticatable $user, string $query = '', int $limit = 10): array
    {
        return Link::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->when($query !== '', fn ($q) => $q->where(fn ($sub) => $sub->where('custom_title', 'like', "%{$query}%")
                ->orWhere('target_url', 'like', "%{$query}%")
                ->orWhere('slug', 'like', "%{$query}%")))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Link $link) => ['title' => $link->custom_title ?: 'Untitled'] + self::row($link))
            ->values()
            ->all();
    }

    /** @return array{id: int, title: ?string, url: string, short_url: string} */
    private static function row(Link $link): array
    {
        return ['id' => $link->id, 'title' => $link->custom_title, 'url' => $link->target_url, 'short_url' => $link->short_url];
    }
}
