<?php

namespace App\Services;

use App\Models\User;
use Hop\Bio\Support\BioUploads;
use Illuminate\Support\Facades\Storage;

/**
 * Removes what this app holds for an account: links (with QR codes, tags and
 * comments), folders, tags and, while bio runs here, bio pages and their
 * uploads. The account itself is the hub's: it deletes the `users` row (the
 * hub calls DELETE /internal/accounts/{id} here first, see AccountDataController)
 * and the HOP admin API (Imperium) may delete a whole account too.
 */
class UserDeletion
{
    public function __construct(private readonly LinkService $links) {}

    /**
     * This app's data only; the user row stays.
     *
     * @return array{links: int, folders: int, tags: int, bio_pages: int}
     */
    public function purge(User $user): array
    {
        $bioFiles = bio_enabled() ? BioUploads::ofUser($user) : [];
        $stats = ['links' => 0, 'folders' => 0, 'tags' => 0, 'bio_pages' => 0];
        $user->links()->each(function ($link) use (&$stats) {
            if ($link->qr_code_path) {
                Storage::disk('public')->delete($link->qr_code_path);
            }
            $link->tags()->detach();
            $link->comments()->delete();
            $link->delete();
            $this->links->forgetCache($link);
            $stats['links']++;
        });
        $stats['folders'] = $user->folders()->delete();
        $stats['tags'] = $user->tags()->delete();
        if (bio_enabled()) {
            $stats['bio_pages'] = $user->bioPages()->count();
            $user->bioPages()->each(fn ($page) => $page->delete());
            BioUploads::deleteUnused($bioFiles);
        }

        return $stats;
    }

    /** Everything, account included (the HOP admin API). */
    public function delete(User $user): void
    {
        $this->purge($user);
        $user->delete();
    }
}
