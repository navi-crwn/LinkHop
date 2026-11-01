<?php

namespace App\Mail;

use App\Models\Link;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "The page behind your short link has stopped answering", sent once per episode by the destination monitor. */
class LinkDestinationBroken extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Link $link, public ?int $status = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Destination not responding: :link', ['link' => '/l/'.$this->link->slug]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.link-destination-broken');
    }
}
