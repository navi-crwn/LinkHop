<?php

namespace App\Jobs;

use App\Models\HopApp;
use App\Support\HopSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tells one app that something happened to an account (HOP App Protocol §6):
 * POST {app}/hop/events with {id, type, at, data}, signed with the app's
 * secret. Retried with backoff for about a day; the app must be idempotent on
 * `id`. Only apps that declare the `events` capability get these.
 */
class SendHopEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    /** @return list<int> seconds */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 3600, 7200, 14400, 28800];
    }

    public function __construct(
        public readonly int $appId,
        public readonly string $type,
        public readonly array $data,
        public readonly string $eventId = '',
        public readonly string $at = '',
    ) {}

    /** Queue the event for every app that listens. */
    public static function broadcast(string $type, array $data): void
    {
        try {
            $apps = HopApp::registered()->get(); // hidden apps (Imperium) may listen too
        } catch (\Throwable) {
            return; // table missing on a fresh install
        }
        $id = (string) Str::uuid();
        $at = now()->toIso8601String();
        foreach ($apps as $app) {
            if ($app->isConfigured() && $app->can('events')) {
                self::dispatch($app->id, $type, $data, $id, $at);
            }
        }
    }

    public function handle(): void
    {
        $app = HopApp::find($this->appId);
        if (! $app || ! $app->isConfigured() || ! $app->can('events')) {
            return;
        }
        $body = json_encode(['id' => $this->eventId, 'type' => $this->type, 'at' => $this->at, 'data' => $this->data], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = '/hop/events';
        $res = Http::timeout(10)->withHeaders(HopSignature::headers($app, 'POST', $path, $body) + ['Content-Type' => 'application/json'])
            ->withBody($body, 'application/json')->post($app->baseUrl().$path);
        if (! $res->successful()) {
            Log::warning('HOP event not accepted', ['app' => $app->slug, 'type' => $this->type, 'status' => $res->status()]);
            $res->throw();
        }
    }
}
