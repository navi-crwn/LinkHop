<?php

namespace App\Http\Middleware;

use App\Services\QuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLinkQuota
{
    public function __construct(private readonly QuotaService $quotas) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            // Counted on every request: a cached answer would let a full account through (or keep a
            // freed one out) for minutes. The insert itself is checked again under a lock, see
            // QuotaService::createWithinLimits.
            if (! $this->quotas->checkUserLimits($request->user()->id)) {
                return response('Link quota exceeded.', Response::HTTP_TOO_MANY_REQUESTS);
            }
        } else {
            $ip = $request->ip();
            $quotaExceeded = cache()->remember("guest-quota:{$ip}", now()->addMinutes(10), function () use ($ip) {
                return ! $this->quotas->checkGuestLimits($ip);
            });
            if ($quotaExceeded) {
                return response('Guest quota exceeded.', Response::HTTP_TOO_MANY_REQUESTS);
            }
        }

        return $next($request);
    }
}
