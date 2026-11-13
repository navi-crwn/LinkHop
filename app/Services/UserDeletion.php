<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\Link;
use App\Models\QrCode;
use App\Models\Tag;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Reminders;
use Hop\Bio\Models\BioPage;
use Hop\Bio\Support\BioUploads;
use Illuminate\Support\Facades\DB;
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
        return $this->purgeData((int) $user->getKey());
    }

    /**
     * Purge by user ID without needing the user row (the hub may already have
     * deleted it), and delete any explicit QR or bio upload paths the hub supplied.
     * Everything is found through the `user_id` columns, so a second call finds
     * nothing left and returns zero counts. Links whose owner row was already
     * removed by the foreign key (`links.user_id` is set to null then) can no
     * longer be tied to the account: the hub has to call this before it deletes
     * the row.
     *
     * @param  iterable<string>  $qrCodes
     * @param  iterable<string>  $bioUploads
     * @return array{links: int, folders: int, tags: int, bio_pages: int}
     */
    public function purgeById(int $userId, iterable $qrCodes = [], iterable $bioUploads = []): array
    {
        $stats = $userId > 0 ? $this->purgeData($userId) : ['links' => 0, 'folders' => 0, 'tags' => 0, 'bio_pages' => 0];
        $this->deleteQrFiles($qrCodes);
        if (bio_enabled()) {
            BioUploads::deleteUnused($bioUploads);
        }

        return $stats;
    }

    /** @return array{links: int, folders: int, tags: int, bio_pages: int} */
    private function purgeData(int $userId): array
    {
        $stats = ['links' => 0, 'folders' => 0, 'tags' => 0, 'bio_pages' => 0];
        // first, so deleting the links below sends no "deleted" events to an account that is going away
        $endpointIds = WebhookEndpoint::where('user_id', $userId)->pluck('id');
        WebhookDelivery::whereIn('webhook_endpoint_id', $endpointIds)->delete();
        WebhookEndpoint::whereKey($endpointIds)->delete();
        DB::table('api_idempotency_keys')->where('user_id', $userId)->delete();
        Reminders::forget($userId);
        // the saved Wi-Fi, contact and text codes (their payload is encrypted at rest; the rows go with the account)
        QrCode::where('user_id', $userId)->delete();

        $bioPages = bio_enabled() ? BioPage::where('user_id', $userId)->get() : collect();
        $bioFiles = $bioPages->flatMap(fn (BioPage $page) => BioUploads::ofPage($page))->all();

        Link::where('user_id', $userId)->each(function (Link $link) use (&$stats) {
            if ($link->qr_code_path) {
                Storage::disk('public')->delete($link->qr_code_path);
            }
            $link->tags()->detach();
            $link->comments()->delete();
            $link->clicks()->delete();
            $link->delete();
            $this->links->forgetCache($link);
            $stats['links']++;
        });
        $stats['folders'] = Folder::where('user_id', $userId)->delete();
        $stats['tags'] = Tag::where('user_id', $userId)->delete();
        if (bio_enabled()) {
            $stats['bio_pages'] = $bioPages->count();
            $bioPages->each(fn (BioPage $page) => $page->delete());
            BioUploads::deleteUnused($bioFiles);
        }

        return $stats;
    }

    /** @param iterable<string> $paths */
    public function deleteQrFiles(iterable $paths): void
    {
        foreach ($paths as $raw) {
            $path = preg_replace('#^/?storage/#', '', trim((string) $raw));
            if (preg_match('#^(qr|qrcodes)/[A-Za-z0-9._-]+$#', (string) $path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /** Everything, account included (the HOP admin API). */
    public function delete(User $user): void
    {
        $this->purge($user);
        $user->delete();
    }
}
