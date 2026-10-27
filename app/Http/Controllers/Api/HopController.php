<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HopApp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HOP ID's side of the HOP App Protocol: what the apps and Imperium may read
 * about accounts. Every route is signed (VerifyHopSignature).
 */
class HopController extends Controller
{
    /** GET /api/hop/profile/{id}: one account's HOP profile. */
    public function profile(Request $request, User $user): JsonResponse
    {
        /** @var HopApp $app */
        $app = $request->attributes->get('hop_app');

        return response()->json(['ok' => true, 'data' => $user->hopProfile() + ['admin' => $user->isAdminOf($app->slug)]]);
    }

    /**
     * GET /api/hop/profiles?ids=1,2,3 or ?since=<iso> or ?cursor=: profiles in
     * bulk, for an app that wants to refresh its local copies.
     */
    public function profiles(Request $request): JsonResponse
    {
        /** @var HopApp $app */
        $app = $request->attributes->get('hop_app');
        $q = User::query()->orderBy('id');
        if ($ids = $request->query('ids')) {
            $q->whereIn('id', array_slice(array_filter(array_map('intval', explode(',', (string) $ids))), 0, 200));
        }
        if ($since = $request->query('since')) {
            $q->where('updated_at', '>=', $since);
        }
        if ($cursor = (int) $request->query('cursor', 0)) {
            $q->where('id', '>', $cursor);
        }
        $rows = $q->limit(200)->get();
        $data = $rows->map(fn (User $u) => $u->hopProfile() + ['admin' => $u->isAdminOf($app->slug)])->all();

        return response()->json(['ok' => true, 'data' => $data, 'page' => ['next' => $rows->count() === 200 ? (string) $rows->last()->id : null]]);
    }

    /**
     * GET /api/hop/apps: the registry, for Imperium's menu and health checks.
     * An admin client (Imperium) also gets each app's secret, so it can sign
     * its own calls to the apps; everyone else gets the registry without secrets.
     */
    public function apps(Request $request): JsonResponse
    {
        /** @var HopApp $caller */
        $caller = $request->attributes->get('hop_app');
        $withSecret = $caller->can('admin_client');
        $apps = HopApp::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (HopApp $a) => [
            'slug' => $a->slug, 'name' => $a->name, 'url' => $a->url, 'status' => $a->status, 'icon' => $a->icon,
            'color' => $a->color, 'capabilities' => $a->capabilities ?? [], 'menu_label' => $a->label(), 'hidden' => (bool) $a->getAttribute('hidden'),
        ] + ($withSecret ? ['secret' => $a->secret] : []));

        return response()->json(['ok' => true, 'data' => $apps]);
    }
}
