<?php

namespace App\Console\Commands;

use App\Support\Webhooks;
use Illuminate\Console\Command;

class WebhooksExpired extends Command
{
    protected $signature = 'webhooks:expired';

    protected $description = 'Send link.expired to webhooks for links that ran out of time since the last sweep';

    public function handle(): int
    {
        $this->info(Webhooks::sweepExpired().' events queued.');

        return self::SUCCESS;
    }
}
