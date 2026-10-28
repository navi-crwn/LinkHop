<?php

namespace Hop\Bio\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Short links for a bio block ("shorten this URL" in the editor). The host app
 * binds it to its shortener; BioHop will call LinkHop's API through the same door.
 */
interface ShortlinkCreator
{
    /**
     * The user's short link for $url, reusing one that already points there.
     *
     * @return array{id: int|string, title: ?string, url: string, short_url: string, reused: bool}
     */
    public function findOrCreate(Authenticatable $user, string $url, ?string $title = null): array;

    /**
     * The user's short links matching $query (title, target or slug), newest first.
     *
     * @return list<array{id: int|string, title: string, url: string, short_url: string}>
     */
    public function search(Authenticatable $user, string $query = '', int $limit = 10): array;
}
