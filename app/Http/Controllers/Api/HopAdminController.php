<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbuseFlag;
use App\Models\AbuseReport;
use App\Models\HopApp;
use App\Models\HopAudit;
use App\Models\HopUserNote;
use App\Models\Link;
use App\Models\Role;
use App\Models\User;
use App\Services\UserDeletion;
use Carbon\CarbonImmutable;
use Hop\Bio\Models\BioPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The HOP admin API for Imperium (HOP App Protocol): stats, accounts, admin
 * actions, audit trail, search and health. Every route is signed and, except
 * /health, reserved for an app with the `admin_client` capability.
 */
class HopAdminController extends Controller
{
    public const ACTIONS = ['suspend', 'activate', 'revoke_sessions', 'revoke_tokens', 'set_roles', 'note', 'delete'];

    // ---- stats ------------------------------------------------------------

    /** GET /api/hop/stats?since=&until=: KPIs and daily series (14 days by default). */
    public function stats(Request $request): JsonResponse
    {
        $until = $this->day($request->query('until')) ?? CarbonImmutable::now('UTC')->endOfDay();
        $since = $this->day($request->query('since')) ?? $until->subDays(13)->startOfDay();
        if ($since->gt($until)) {
            return $this->fail('bad_range', 'since must not be after until', 422);
        }
        $data = Cache::remember('hop:stats:'.$since->toDateString().':'.$until->toDateString(), 60, function () use ($since, $until) {
            $now = CarbonImmutable::now('UTC');

            return [
                'kpi' => [
                    'users' => User::count(),
                    'users_new_7d' => User::where('created_at', '>=', $now->subDays(7))->count(),
                    'users_suspended' => User::where('status', User::STATUS_SUSPENDED)->count(),
                    'links' => Link::count(),
                    'links_live' => Link::query()->live()->count(),
                    'bio_pages' => bio_enabled() ? BioPage::count() : 0,
                    'clicks_30d' => DB::table('link_clicks')->where('clicked_at', '>=', $now->subDays(30))->count(),
                    'clicks_today' => DB::table('link_clicks')->where('clicked_at', '>=', $now->startOfDay())->count(),
                    'open_reports' => AbuseReport::where('status', 'open')->count(),
                    'open_flags' => AbuseFlag::where('status', AbuseFlag::STATUS_OPEN)->count(),
                ],
                'series' => [
                    'signups' => $this->daily('users', 'created_at', $since, $until),
                    'clicks' => $this->daily('link_clicks', 'clicked_at', $since, $until),
                ],
            ];
        });

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /** @return list<array{0: string, 1: int}> one [date, n] per day in the range, zeros included */
    private function daily(string $table, string $column, CarbonImmutable $since, CarbonImmutable $until): array
    {
        $rows = DB::table($table)->selectRaw("DATE($column) as d, COUNT(*) as n")
            ->whereBetween($column, [$since, $until])->groupBy('d')->pluck('n', 'd');
        $out = [];
        for ($day = $since->startOfDay(); $day->lte($until); $day = $day->addDay()) {
            $out[] = [$day->toDateString(), (int) ($rows[$day->toDateString()] ?? 0)];
        }

        return $out;
    }

    private function day(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value, 'UTC');
        } catch (\Throwable) {
            return null;
        }
    }

    // ---- users ------------------------------------------------------------

    /**
     * GET /api/hop/users?q=&status=&role=&cursor=&limit=: accounts, newest
     * first (by id). `cursor` is the last id of the previous page.
     */
    public function users(Request $request): JsonResponse
    {
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $q = User::query()->with('roles')->withCount(bio_enabled() ? ['links', 'bioPages'] : ['links']);
        $this->matchUsers($q, trim((string) $request->query('q', '')));
        if (in_array($status = (string) $request->query('status', ''), [User::STATUS_ACTIVE, User::STATUS_SUSPENDED], true)) {
            $q->where('status', $status);
        }
        if ($role = (string) $request->query('role', '')) {
            [$name, $app] = array_pad(explode(':', $role, 2), 2, null);
            $q->whereHas('roles', fn (Builder $r) => $r->where('role', $name)->where('app_slug', $app));
        }
        $total = (clone $q)->count();
        if ($cursor = (int) $request->query('cursor', 0)) {
            $q->where('id', '<', $cursor);
        }
        $rows = $q->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'ok' => true,
            'data' => $rows->map(fn (User $u) => $this->summary($u))->all(),
            'page' => ['next' => $rows->count() === $limit ? (string) $rows->last()->id : null, 'total' => $total],
        ]);
    }

    private function matchUsers(Builder $q, string $term): void
    {
        if ($term === '') {
            return;
        }
        $q->where(function (Builder $w) use ($term) {
            $w->where('username', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
            if (ctype_digit($term)) {
                $w->orWhere('id', (int) $term);
            }
        });
    }

    /** The list row for an account. */
    private function summary(User $u): array
    {
        return [
            'sub' => (string) $u->id,
            'username' => $u->username,
            'name' => $u->name,
            'email' => $u->email,
            'status' => $u->status,
            'roles' => $u->roleNames(),
            'verified' => $u->hasVerifiedEmail() || (bool) $u->google_id,
            'created_at' => $u->created_at?->toIso8601String(),
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'links' => (int) ($u->links_count ?? $u->links()->count()),
            'bio_pages' => bio_enabled() ? (int) ($u->bio_pages_count ?? $u->bioPages()->count()) : 0,
        ];
    }

    /** GET /api/hop/users/{user}: the "User 360" view. */
    public function user(User $user): JsonResponse
    {
        $monthAgo = CarbonImmutable::now('UTC')->subDays(30);
        $session = DB::table('sessions')->where('user_id', $user->id)->selectRaw('COUNT(*) as n, MAX(last_activity) as last')->first();

        return response()->json(['ok' => true, 'data' => [
            'profile' => $user->hopProfile() + ['admin' => $user->isAdmin(), 'owner' => $user->isOwner(), 'moderator' => $user->isModerator()],
            'counts' => [
                'links' => $user->links()->count(),
                'links_live' => $user->links()->live()->count(),
                'bio_pages' => bio_enabled() ? $user->bioPages()->count() : 0,
                'folders' => $user->folders()->count(),
                'tags' => $user->tags()->count(),
                'api_tokens' => $user->apiTokens()->count(),
                'clicks_30d' => DB::table('link_clicks')->join('links', 'links.id', '=', 'link_clicks.link_id')
                    ->where('links.user_id', $user->id)->where('link_clicks.clicked_at', '>=', $monthAgo)->count(),
            ],
            'sessions' => [
                'count' => (int) ($session->n ?? 0),
                'last_activity' => $session && $session->last ? CarbonImmutable::createFromTimestampUTC((int) $session->last)->toIso8601String() : null,
            ],
            'logins' => $user->loginHistories()->orderByDesc('logged_in_at')->limit(5)->get()->map(fn ($l) => [
                'at' => $l->logged_in_at?->toIso8601String(), 'ip' => $l->ip_address, 'country' => $l->country, 'city' => $l->city,
                'device' => $l->device, 'browser' => $l->browser, 'provider' => $l->provider, 'is_proxy' => (bool) $l->is_proxy,
            ])->all(),
            'activity' => $user->activityLogs()->orderByDesc('id')->limit(20)->get()->map(fn ($a) => [
                'at' => $a->created_at?->toIso8601String(), 'action' => $a->action, 'model' => $a->model, 'description' => $a->description,
            ])->all(),
            'roles' => $user->roleNames(),
            'notes' => HopUserNote::where('user_id', $user->id)->orderByDesc('id')->get()->map(fn ($n) => [
                'id' => $n->id, 'author' => $n->author, 'text' => $n->text, 'created_at' => $n->created_at?->toIso8601String(),
            ])->all(),
            'recent_links' => $user->links()->orderByDesc('id')->limit(10)->get()->map(fn ($l) => [
                'slug' => $l->slug, 'target_url' => $l->target_url, 'status' => $l->status, 'clicks' => (int) $l->clicks,
                'created_at' => $l->created_at?->toIso8601String(),
            ])->all(),
        ]]);
    }

    // ---- actions ----------------------------------------------------------

    /** POST /api/hop/users/{user}/actions {action, reason, params}: an admin action, audited. */
    public function act(Request $request, User $user, UserDeletion $deletion): JsonResponse
    {
        /** @var HopApp $app */
        $app = $request->attributes->get('hop_app');
        $action = (string) $request->input('action', '');
        $reason = $request->input('reason');
        $params = (array) $request->input('params', []);
        if (! in_array($action, self::ACTIONS, true)) {
            return $this->fail('unknown_action', 'action must be one of '.implode(', ', self::ACTIONS), 422);
        }
        if (in_array($action, ['suspend', 'delete'], true) && $user->isOwner()) {
            return $this->fail('owner_protected', 'The owner account cannot be suspended or deleted.', 403);
        }
        $actor = (string) $request->header('X-HOP-Actor', 'system');
        $audit = ['actor' => $actor, 'app_slug' => $app->slug, 'action' => $action, 'target_type' => 'user',
            'target_id' => (string) $user->id, 'reason' => is_string($reason) ? mb_substr($reason, 0, 500) : null];

        switch ($action) {
            case 'suspend':
                $user->update(['status' => User::STATUS_SUSPENDED]);
                break;
            case 'activate':
                $user->update(['status' => User::STATUS_ACTIVE]);
                break;
            case 'revoke_sessions':
                $params = ['deleted' => DB::table('sessions')->where('user_id', $user->id)->delete()];
                break;
            case 'revoke_tokens':
                $params = ['deleted' => $user->apiTokens()->delete()];
                break;
            case 'set_roles':
                $roles = $params['roles'] ?? null;
                if (! is_array($roles)) {
                    return $this->fail('bad_params', 'params.roles must be a list of role strings', 422);
                }
                $parsed = $this->parseRoles($roles);
                if ($parsed === null) {
                    return $this->fail('bad_params', 'unknown role; use owner, admin, moderator, support or admin:<app>', 422);
                }
                $keepsOwner = in_array(Role::OWNER, array_column($parsed, 0), true);
                if ($user->hasRole(Role::OWNER) && ! $keepsOwner && Role::where('role', Role::OWNER)->whereNull('app_slug')->count() <= 1) {
                    return $this->fail('last_owner', 'HOP ID must keep at least one owner.', 409);
                }
                $actorId = ctype_digit($actor) && User::whereKey((int) $actor)->exists() ? (int) $actor : null;
                DB::transaction(function () use ($user, $parsed, $actorId) {
                    $user->roles()->delete();
                    foreach ($parsed as [$role, $slug]) {
                        $user->roles()->create(['role' => $role, 'app_slug' => $slug, 'granted_by' => $actorId]);
                    }
                });
                $user->unsetRelation('roles');
                $params = ['roles' => array_values(array_unique($roles))];
                break;
            case 'note':
                $text = trim((string) ($params['text'] ?? ''));
                if ($text === '') {
                    return $this->fail('bad_params', 'params.text is required', 422);
                }
                $note = HopUserNote::create(['user_id' => $user->id, 'author' => $actor, 'text' => mb_substr($text, 0, 5000)]);
                $params = ['note_id' => $note->id];
                break;
            case 'delete':
                $summary = $this->summary($user) + ['deleted' => true];
                $deletion->delete($user);
                HopAudit::create($audit + ['params' => ['email' => $summary['email'], 'username' => $summary['username']]]);

                return response()->json(['ok' => true, 'data' => $summary]);
        }
        HopAudit::create($audit + ['params' => $params]);

        return response()->json(['ok' => true, 'data' => $this->summary($user->fresh())]);
    }

    /**
     * "admin", "admin:pixelhop" → [[role, app_slug|null], …]; null when a role is unknown.
     *
     * @return list<array{0: string, 1: ?string}>|null
     */
    private function parseRoles(array $roles): ?array
    {
        $out = [];
        foreach ($roles as $r) {
            if (! is_string($r)) {
                return null;
            }
            [$name, $slug] = array_pad(explode(':', $r, 2), 2, null);
            if (! in_array($name, Role::GLOBAL, true) || ($slug !== null && ($name !== Role::ADMIN || ! preg_match('/^[a-z0-9-]{1,32}$/', $slug)))) {
                return null;
            }
            $out[$name.':'.$slug] = [$name, $slug];
        }

        return array_values($out);
    }

    // ---- audit, search, health -------------------------------------------

    /** GET /api/hop/audit?cursor=&limit=: admin actions, newest first; `cursor` is the last id seen. */
    public function audit(Request $request): JsonResponse
    {
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $q = HopAudit::query();
        if ($target = (string) $request->query('target', '')) {
            $q->where('target_id', $target);
        }
        $total = (clone $q)->count();
        if ($cursor = (int) $request->query('cursor', 0)) {
            $q->where('id', '<', $cursor);
        }
        $rows = $q->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'ok' => true,
            'data' => $rows->map->toApi()->all(),
            'page' => ['next' => $rows->count() === $limit ? (string) $rows->last()->id : null, 'total' => $total],
        ]);
    }

    /** GET /api/hop/search?q=: up to 20 users, links and bio pages. */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            return response()->json(['ok' => true, 'data' => []]);
        }
        $out = [];
        $users = User::query();
        $this->matchUsers($users, $term);
        foreach ($users->orderByDesc('id')->limit(20)->get() as $u) {
            $out[] = ['kind' => 'user', 'id' => (string) $u->id, 'label' => ($u->username ? '@'.$u->username.' ' : '').$u->name.' <'.$u->email.'>',
                'sub' => (string) $u->id, 'url' => null];
        }
        $links = Link::query()->where(fn ($w) => $w->where('slug', 'like', "%{$term}%")->orWhere('target_url', 'like', "%{$term}%"))
            ->orderByDesc('id')->limit(max(0, 20 - count($out)))->get();
        foreach ($links as $l) {
            $out[] = ['kind' => 'link', 'id' => (string) $l->id, 'label' => '/'.$l->slug.' → '.$l->target_url,
                'sub' => $l->user_id ? (string) $l->user_id : null, 'url' => $l->short_url];
        }
        $bios = ! bio_enabled() ? collect() : BioPage::query()->where(fn ($w) => $w->where('slug', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%"))
            ->orderByDesc('id')->limit(max(0, 20 - count($out)))->get();
        foreach ($bios as $b) {
            $out[] = ['kind' => 'bio', 'id' => (string) $b->id, 'label' => $b->title.' (/'.$b->slug.')',
                'sub' => $b->user_id ? (string) $b->user_id : null, 'url' => public_url('b/'.$b->slug)];
        }

        return response()->json(['ok' => true, 'data' => array_slice($out, 0, 20)]);
    }

    /** GET /api/hop/health: liveness for Imperium (signed, any app). */
    public function health(): JsonResponse
    {
        try {
            DB::select('select 1');
            $db = 'ok';
        } catch (\Throwable) {
            $db = 'error';
        }
        $beat = Cache::get('queue:heartbeat');
        $queue = 'stale';
        if ($beat) {
            try {
                $queue = CarbonImmutable::parse((string) $beat)->gt(CarbonImmutable::now()->subMinutes(10)) ? 'ok' : 'stale';
            } catch (\Throwable) {
            }
        }

        return response()->json(['ok' => true, 'data' => [
            'app' => 'linkhop', 'version' => self::version(), 'db' => $db, 'queue' => $queue,
            'time' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]]);
    }

    /** The short git commit of the checkout, or "dev". */
    public static function version(): string
    {
        return Cache::remember('hop:version', 300, function () {
            try {
                $head = trim((string) @file_get_contents(base_path('.git/HEAD')));
                if (str_starts_with($head, 'ref: ')) {
                    $ref = substr($head, 5);
                    $head = trim((string) @file_get_contents(base_path('.git/'.$ref)));
                    if ($head === '' && is_file(base_path('.git/packed-refs'))) {
                        foreach (file(base_path('.git/packed-refs'), FILE_IGNORE_NEW_LINES) as $line) {
                            if (str_ends_with($line, ' '.$ref)) {
                                $head = substr($line, 0, 40);
                            }
                        }
                    }
                }

                return preg_match('/^[0-9a-f]{40}$/', $head) ? substr($head, 0, 7) : 'dev';
            } catch (\Throwable) {
                return 'dev';
            }
        });
    }

    private function fail(string $error, string $message, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $error, 'message' => $message], $status);
    }
}
