<?php

namespace Hop\Bio\Support;

use App\Models\User;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Illuminate\Support\Facades\Storage;

/**
 * Files uploaded for bio pages (avatars, backgrounds, thumbnails, icons, QR logos).
 * Duplicated pages share files, so a file is only deleted once nothing refers to it.
 */
class BioUploads
{
    /** @return array<int, string|null> */
    public static function ofPage(BioPage $bioPage): array
    {
        $files = [$bioPage->avatar_url, $bioPage->background_image, $bioPage->qr_settings['image'] ?? null, $bioPage->qr_settings['logo_url'] ?? null];
        foreach ($bioPage->links()->get(['thumbnail_url', 'custom_icon', 'content']) as $link) {
            array_push($files, ...self::ofLink($link));
        }

        return $files;
    }

    /** @return array<int, string|null> */
    public static function ofLink(BioLink $link): array
    {
        return [$link->thumbnail_url, $link->custom_icon, $link->content];
    }

    /** @return array<int, string|null> */
    public static function ofUser(User $user): array
    {
        return $user->bioPages()->get()->flatMap(fn (BioPage $page) => self::ofPage($page))->all();
    }

    /** Delete each upload that no page or block refers to any more. */
    public static function deleteUnused(iterable $paths): void
    {
        foreach ($paths as $path) {
            self::deleteIfUnused($path);
        }
    }

    public static function deleteIfUnused(?string $path): void
    {
        $path = preg_replace('#^/?storage/#', '', trim((string) $path));
        if (! preg_match('#^(avatars|bio-images|bio-thumbnails|bio-icons|qr-logos)/[A-Za-z0-9._-]+$#', $path)) {
            return;
        }
        $like = '%'.$path.'%';
        $inUse = BioPage::where(fn ($q) => $q->where('avatar_url', 'like', $like)
            ->orWhere('qr_settings', 'like', $like)
            ->orWhere('background_value', 'like', $like)
            ->orWhere('background_image', 'like', $like))->exists()
            || BioLink::where(fn ($q) => $q->where('thumbnail_url', 'like', $like)
                ->orWhere('custom_icon', 'like', $like)
                ->orWhere('content', 'like', $like))->exists();
        if (! $inUse) {
            Storage::disk('public')->delete($path);
        }
    }
}
