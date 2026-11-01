<?php

namespace App\Console\Commands;

use App\Support\Webhooks;
use Illuminate\Console\Command;

class WebhooksRetry extends Command
{
    protected $signature = 'webhooks:retry';

    protected $description = 'Queue the webhook deliveries whose next try is due';

    public function handle(): int
    {
        $this->info(Webhooks::retryDue().' deliveries queued.');

        return self::SUCCESS;
    }
}
