<?php

namespace App\Services;

use App\Support\EncryptedNoteLink;
use Hop\Safety\LinkSafety;

/**
 * hop/safety's LinkSafety plus two rules that belong to this app, bound over the package's in AppServiceProvider.
 *
 * Every place that accepts a destination asks rejectionReason() first (web forms, the API, CSV import, the
 * routing and app-link fields, the bio blocks), so the encrypted-note rule lives here once.
 */
class LinkHopSafety extends LinkSafety
{
    public function __construct(private readonly EncryptedNoteLink $notes) {}

    /** Refuses the address of an encrypted NoteHop note: its key is in the fragment, and we would store it. */
    public function rejectionReason(?string $url, string $text = '', array $schemes = self::WEB_SCHEMES): ?string
    {
        if ($this->notes->matches($url)) {
            return $this->notes->message();
        }

        return parent::rejectionReason($url, $text, $schemes);
    }

    /**
     * A fragment never reaches the destination server, so it is not sent to the outside lookups (Safe Browsing,
     * URLhaus) either. The keyword patterns still read the whole address.
     */
    public function assess(?string $url, string $text = '', bool $remote = false): array
    {
        $bare = EncryptedNoteLink::withoutFragment($url);
        if (! $remote || $bare === $url) {
            return parent::assess($url, $text, $remote);
        }
        $local = parent::assess($url, $text, false);
        $far = parent::assess($bare, $text, true);
        $signals = array_values(array_unique([...$local['signals'], ...$far['signals']]));
        $weights = config('safety.weights', []);

        return ['score' => array_sum(array_map(fn ($s) => (int) ($weights[$s] ?? 0), $signals)), 'signals' => $signals];
    }
}
