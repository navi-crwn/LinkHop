<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Idempotency-Key` on the token API's create endpoints. The first request with a key does the work and its
 * successful answer is kept for 24 hours; the same key from the same account with the same content gets that
 * answer again (header `Idempotent-Replayed: true`) and creates nothing. The same key with other content is a 422.
 *
 * The unique index on (user_id, key) is the lock: the insert of the first request wins, so two requests that
 * arrive together cannot both create. The second gets a 409 until the first has finished. Only 2xx answers are
 * kept; a failed request can be sent again with the same key once the cause is fixed.
 * Must run after the token middleware (it needs the request's user).
 */
class IdempotentRequest
{
    public const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user();
        if ($key === null || $user === null) {
            return $next($request);
        }
        $key = trim($key);
        if (! preg_match('/^[\x21-\x7e]{1,255}$/', $key)) {
            return $this->error(422, 'Invalid Idempotency Key', 'The Idempotency-Key header has to be 1 to 255 printable characters without spaces.');
        }
        $fingerprint = $this->fingerprint($request);
        $table = DB::table('api_idempotency_keys');
        $row = ['user_id' => $user->id, 'key' => $key, 'fingerprint' => $fingerprint, 'expires_at' => now()->addHours(self::TTL_HOURS), 'created_at' => now()];
        $id = null;
        for ($try = 0; $try < 2 && $id === null; $try++) {
            try {
                $id = $table->insertGetId($row);
            } catch (UniqueConstraintViolationException) {
                $existing = DB::table('api_idempotency_keys')->where('user_id', $user->id)->where('key', $key)->first();
                if (! $existing) {
                    continue; // deleted in between: try to take it again
                }
                if ($existing->expires_at <= now()->toDateTimeString()) {
                    DB::table('api_idempotency_keys')->where('id', $existing->id)->delete();

                    continue;
                }

                return $this->existing($existing, $fingerprint);
            }
        }
        if ($id === null) {
            return $this->error(409, 'Conflict', 'Another request with this Idempotency-Key is being processed. Try again in a moment.', ['Retry-After' => '1']);
        }
        $kept = false;
        try {
            $response = $next($request);
            if ($response->isSuccessful()) {
                DB::table('api_idempotency_keys')->where('id', $id)->update([
                    'status_code' => $response->getStatusCode(),
                    'response_body' => (string) $response->getContent(),
                ]);
                $kept = true;
            }
            $response->headers->set('Idempotency-Key', $key);

            return $response;
        } finally {
            if (! $kept) {
                DB::table('api_idempotency_keys')->where('id', $id)->delete();
            }
        }
    }

    private function existing(object $row, string $fingerprint): Response
    {
        if (! hash_equals($row->fingerprint, $fingerprint)) {
            return $this->error(422, 'Idempotency Key Reuse', 'This Idempotency-Key was already used with a different request. Use a new key for a new request.');
        }
        if ($row->status_code === null) {
            return $this->error(409, 'Conflict', 'Another request with this Idempotency-Key is being processed. Try again in a moment.', ['Retry-After' => '1']);
        }

        return response($row->response_body, (int) $row->status_code, [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
            'Idempotency-Key' => $row->key,
        ]);
    }

    /** The same request in another key order or spacing is the same request. */
    private function fingerprint(Request $request): string
    {
        $body = $request->all();
        $sort = function (&$value) use (&$sort) {
            if (is_array($value)) {
                ksort($value);
                array_walk($value, $sort);
            }
        };
        $sort($body);

        return hash('sha256', $request->method()."\n".$request->path()."\n".json_encode($body));
    }

    /** @param array<string, string> $headers */
    private function error(int $status, string $error, string $message, array $headers = []): Response
    {
        return response()->json(['success' => false, 'error' => $error, 'message' => $message], $status, $headers);
    }
}
