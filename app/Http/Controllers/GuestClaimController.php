<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\GuestClaim;
use Illuminate\Http\Request;

/** POST /links/claim: move the guest links this browser made (see App\Support\GuestClaim) to the signed-in account. */
class GuestClaimController extends Controller
{
    public function __invoke(Request $request)
    {
        $token = GuestClaim::token($request);
        $moved = $token === null ? 0 : GuestClaim::claim($token, (int) $request->user()->id);
        if ($moved > 0) {
            ActivityLog::log('claimed', 'Link', 0, "Claimed {$moved} guest link(s) made before signing in");
        }
        $message = $moved === 0
            ? __('There were no links left to claim.')
            : ($moved === 1 ? __('1 link is now in your account.') : __(':count links are now in your account.', ['count' => $moved]));

        // The cookie goes either way: a second claim from this browser finds nothing and has nothing to clear it for.
        return redirect()->route('dashboard')->with('status', $message)->withCookie(GuestClaim::forgetCookie());
    }
}
