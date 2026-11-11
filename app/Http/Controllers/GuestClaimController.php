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
        $result = $token === null ? ['moved' => 0, 'left' => 0, 'max' => 0] : GuestClaim::claim($token, (int) $request->user()->id);
        $moved = $result['moved'];
        if ($moved > 0) {
            ActivityLog::log('claimed', 'Link', 0, "Claimed {$moved} guest link(s) made before signing in");
        }
        $message = $moved === 0
            ? ($result['left'] > 0 ? '' : __('There were no links left to claim.'))
            : ($moved === 1 ? __('1 link is now in your account.') : __(':count links are now in your account.', ['count' => $moved]));
        if ($result['left'] > 0) {
            $message = trim($message.' '.__('Not claimed yet: :left (your account is at its limit of :max active links). Remove or switch off some links, then claim again.', ['left' => $result['left'], 'max' => $result['max']]));
        }
        $response = redirect()->route('dashboard')->with('status', $message);

        // The cookie goes once nothing is left to claim; with links left over it stays, so the claim can be repeated.
        return $result['left'] > 0 ? $response : $response->withCookie(GuestClaim::forgetCookie());
    }
}
