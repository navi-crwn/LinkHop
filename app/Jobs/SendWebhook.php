<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Support\Webhooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** One try at one delivery. Failures are recorded on the delivery and retried by the scheduler (webhooks:retry), not by the queue. */
class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        if ($delivery = WebhookDelivery::find($this->deliveryId)) {
            Webhooks::attempt($delivery);
        }
    }
}
