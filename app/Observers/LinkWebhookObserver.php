<?php

namespace App\Observers;

use App\Models\Link;
use App\Support\Webhooks;

/** Turns link changes, wherever they come from (dashboard, API, import, admin), into webhook events. */
class LinkWebhookObserver
{
    public function created(Link $link): void
    {
        Webhooks::linkEvent($link, 'link.created');
    }

    public function updated(Link $link): void
    {
        Webhooks::linkUpdated($link);
    }

    public function deleted(Link $link): void
    {
        Webhooks::linkEvent($link, 'link.deleted');
    }
}
