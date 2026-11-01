<?php

namespace App\Support;

use App\Jobs\SendWebhook;
use App\Models\Link;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sends an account's link events to the addresses it registered, as signed JSON.
 *
 * Each request carries X-LinkHop-Event, X-LinkHop-Delivery (a unique id, the same on every retry),
 * X-LinkHop-Timestamp and X-LinkHop-Signature: sha256=HMAC_SHA256(secret, "<timestamp>.<body>"). The receiver
 * recomputes it and rejects old timestamps. A try that fails is repeated after 1 minute, 5 minutes, 30 minutes and
 * 2 hours (five tries in all) by the scheduler (webhooks:retry). Only https addresses on public networks are called,
 * on the address checked beforehand and without following redirects (WebhookGuard).
 */
class Webhooks
{
    public const EVENTS = ['link.created', 'link.updated', 'link.deleted', 'link.limit_reached', 'link.expired'];

    public const MAX_ENDPOINTS = 5;

    /** Deliveries kept per endpoint, newest first. */
    public const KEEP = 50;

    public const BACKOFF_MINUTES = [1, 5, 30, 120];

    public const MAX_ATTEMPTS = 5;

    /** An endpoint that fails this many deliveries in a row (after all their tries) is switched off. */
    public const DISABLE_AFTER = 10;

    /** The link fields whose change is worth a link.updated; counters, QR files and timestamps are not. */
    private const WATCHED = [
        'slug', 'target_url', 'status', 'expires_at', 'starts_at', 'max_clicks', 'fallback_url', 'redirect_type',
        'folder_id', 'password_hash', 'content_warning', 'custom_title', 'custom_description', 'custom_image_url',
    ];

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** @return array<string, mixed> */
    public static function linkPayload(Link $link): array
    {
        return [
            'id' => $link->id,
            'slug' => $link->slug,
            'short_url' => $link->short_url,
            'target_url' => $link->target_url,
            'status' => $link->status,
            'redirect_type' => (string) ($link->redirect_type ?? '302'),
            'clicks' => (int) $link->clicks,
            'max_clicks' => $link->max_clicks,
            'expires_at' => $link->expires_at?->toIso8601String(),
            'starts_at' => $link->starts_at?->toIso8601String(),
            'fallback_url' => $link->fallback_url,
            'password_protected' => $link->requiresPassword(),
            'created_at' => $link->created_at?->toIso8601String(),
        ];
    }

    /** link.created and link.deleted (and any event that only needs the link). */
    public static function linkEvent(Link $link, string $event, array $extra = []): void
    {
        self::emit($link->user_id, $event, ['link' => self::linkPayload($link)] + $extra);
    }

    /** link.updated, only when something the owner would care about changed. */
    public static function linkUpdated(Link $link): void
    {
        $changed = array_values(array_intersect(array_keys($link->getChanges()), self::WATCHED));
        if ($changed !== []) {
            // the password hash itself never leaves; the receiver learns that the password changed
            $changed = array_map(fn ($f) => $f === 'password_hash' ? 'password' : $f, $changed);
            self::linkEvent($link, 'link.updated', ['changed' => $changed]);
        }
    }

    /**
     * Called by the redirect after it took a click for a limited link: when that was the last one, link.limit_reached.
     * Many visitors at once can all see the final count, so one cache key lets only the first one send it.
     */
    public static function clickCounted(Link $link): void
    {
        try {
            $count = (int) Link::whereKey($link->id)->value('clicks');
            if ($count !== (int) $link->max_clicks || ! Cache::add('webhook:limit:'.$link->id.':'.$link->max_clicks, 1, now()->addDay())) {
                return;
            }
            self::linkEvent($link->fresh() ?? $link, 'link.limit_reached');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** link.expired for links that ran out of time since the last sweep (scheduled, see webhooks:expired). */
    public static function sweepExpired(): int
    {
        $sent = 0;
        Link::query()->whereNotNull('user_id')->where('status', Link::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())->where('expires_at', '>', now()->subDay())
            ->whereIn('user_id', WebhookEndpoint::where('is_active', true)->select('user_id'))
            ->each(function (Link $link) use (&$sent) {
                // once per link and expiry time, even when the sweeps overlap
                if (Cache::add('webhook:expired:'.$link->id.':'.$link->expires_at->timestamp, 1, now()->addDays(2))) {
                    self::linkEvent($link, 'link.expired');
                    $sent++;
                }
            });

        return $sent;
    }

    /** Queue an event for every active endpoint of the account that asked for it. */
    public static function emit(?int $userId, string $event, array $data): void
    {
        if (! $userId) {
            return;
        }
        // A webhook problem (database, queue) must never undo or slow down the change that caused it.
        try {
            WebhookEndpoint::where('user_id', $userId)->where('is_active', true)->get()
                ->filter(fn (WebhookEndpoint $e) => $e->wants($event))
                ->each(fn (WebhookEndpoint $e) => self::queue($e, $event, $data));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function ping(WebhookEndpoint $endpoint): WebhookDelivery
    {
        return self::queue($endpoint, 'ping', ['message' => 'This is a test event from LinkHop.']);
    }

    public static function queue(WebhookEndpoint $endpoint, string $event, array $data): WebhookDelivery
    {
        $uuid = (string) Str::uuid();
        $payload = json_encode([
            'id' => $uuid, 'event' => $event, 'created_at' => now()->toIso8601String(), 'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $delivery = $endpoint->deliveries()->create(['uuid' => $uuid, 'event' => $event, 'payload' => $payload, 'status' => 'pending']);
        $old = $endpoint->deliveries()->orderByDesc('id')->skip(self::KEEP)->take(200)->pluck('id');
        if ($old->isNotEmpty()) {
            WebhookDelivery::whereIn('id', $old)->delete();
        }
        SendWebhook::dispatch($delivery->id);

        return $delivery;
    }

    /** One try. The row is claimed first, so two workers (or a worker and the scheduler) never send the same try twice. */
    public static function attempt(WebhookDelivery $d): void
    {
        $claimed = WebhookDelivery::whereKey($d->id)->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->update(['next_attempt_at' => now()->addMinutes(5)]);
        if (! $claimed) {
            return;
        }
        $d->refresh();
        $endpoint = $d->endpoint;
        $d->attempts++;
        $code = null;
        $retry = true;
        try {
            if (! $endpoint || (! $endpoint->is_active && $d->event !== 'ping')) {
                $retry = false;
                throw new \RuntimeException('off');
            }
            [$problem, $ips, $host, $port] = app(WebhookGuard::class)->inspect($endpoint->url);
            if ($problem !== null) {
                $retry = false;
                throw new \RuntimeException('blocked');
            }
            $timestamp = (string) time();
            $options = ['allow_redirects' => false];
            if (defined('CURLOPT_RESOLVE') && $ips !== []) {
                $ip = $ips[0];
                $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:".(str_contains($ip, ':') ? "[{$ip}]" : $ip)]];
            }
            $res = Http::timeout(5)->connectTimeout(3)->withOptions($options)->withBody($d->payload, 'application/json')
                ->withHeaders([
                    'User-Agent' => 'LinkHop-Webhook/1',
                    'X-LinkHop-Event' => $d->event,
                    'X-LinkHop-Delivery' => $d->uuid,
                    'X-LinkHop-Timestamp' => $timestamp,
                    'X-LinkHop-Signature' => self::sign((string) $endpoint->secret, $timestamp, $d->payload),
                ])->post($endpoint->url);
            $d->http_code = $res->status();
            if (! $res->successful()) {
                throw new \RuntimeException('http');
            }
            $d->fill(['status' => 'sent', 'error' => null, 'next_attempt_at' => null]);
            $endpoint->forceFill(['failure_streak' => 0, 'last_delivery_at' => now()])->save();
        } catch (\Throwable $e) {
            $code = in_array($e->getMessage(), ['off', 'blocked', 'http'], true) ? $e->getMessage() : ($e instanceof ConnectionException ? 'connect' : 'error');
            $final = ! $retry || $d->attempts >= self::MAX_ATTEMPTS;
            $d->fill([
                'status' => $final ? 'failed' : 'pending',
                'error' => $code,
                'next_attempt_at' => $final ? null : now()->addMinutes(self::BACKOFF_MINUTES[min($d->attempts, count(self::BACKOFF_MINUTES)) - 1]),
            ]);
            if ($endpoint) {
                $endpoint->forceFill(['last_delivery_at' => now()]);
                if ($final && $d->event !== 'ping' && $code !== 'off') {
                    $endpoint->failure_streak++;
                    if ($endpoint->failure_streak >= self::DISABLE_AFTER) {
                        $endpoint->is_active = false;
                    }
                }
                $endpoint->save();
            }
        }
        $d->save();
    }

    /** Called by the scheduler every minute: the tries that are due. */
    public static function retryDue(int $limit = 100): int
    {
        $due = WebhookDelivery::where('status', 'pending')->whereNotNull('next_attempt_at')->where('next_attempt_at', '<=', now())
            ->orderBy('next_attempt_at')->limit($limit)->pluck('id');
        foreach ($due as $id) {
            SendWebhook::dispatch($id);
        }

        return $due->count();
    }
}
