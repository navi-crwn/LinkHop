<?php

namespace App\Services;

use App\Models\User;
use App\Support\BioUploads;
use Illuminate\Support\Facades\Storage;

/**
 * Removes an account and everything it owns: links (with QR codes, tags and
 * comments), folders, tags, bio pages (cascade) and their uploaded files.
 * Used by the admin page and the HOP admin API.
 */
class UserDeletion
{
    public function __construct(private readonly LinkService $links) {}

    public function delete(User $user): void
    {
        $bioFiles = BioUploads::ofUser($user);
        $user->links()->each(function ($link) {
            if ($link->qr_code_path) {
                Storage::disk('public')->delete($link->qr_code_path);
            }
            $link->tags()->detach();
            $link->comments()->delete();
            $link->delete();
            $this->links->forgetCache($link);
        });
        $user->folders()->delete();
        $user->tags()->delete();
        $user->delete();
        // Bio pages and blocks are gone with the user (cascade); remove their files too.
        BioUploads::deleteUnused($bioFiles);
    }
}
