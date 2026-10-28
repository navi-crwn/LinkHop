<?php

namespace App\Mail;

use App\Models\User;
use App\Support\ShortLinkMove;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * "Your short links moved to hel.ink/l/…", with the owner's links in the new
 * form. Sent by links:announce-migration on day 0 and, as a reminder, on day 23.
 */
class ShortLinkMigrationNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Links listed in full; the rest is a count, with a pointer to the dashboard. */
    public const MAX_LISTED = 100;

    public function __construct(public User $user, public Collection $links, public bool $reminder = false) {}

    public function envelope(): Envelope
    {
        $host = public_host();

        return new Envelope(
            subject: ($this->reminder ? 'Reminder: ' : '').'Your short links moved to '.$host.'/l/… (old form ends '.ShortLinkMove::untilLabel().')',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.short-link-migration',
            with: [
                'host' => public_host(),
                'until' => ShortLinkMove::untilLabel(),
                'listed' => $this->links->take(self::MAX_LISTED),
                'more' => max(0, $this->links->count() - self::MAX_LISTED),
            ],
        );
    }
}
