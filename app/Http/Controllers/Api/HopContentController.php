<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbuseFlag;
use App\Models\AbuseReport;
use App\Models\HopApp;
use App\Models\HopAudit;
use App\Models\IpBan;
use App\Models\IpWatchlist;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\SeoSetting;
use App\Models\User;
use App\Services\LinkService;
use Carbon\Carbon;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Hop\Bio\Support\BioUploads;
use Hop\Safety\Models\DomainBlacklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * HOP App Protocol, content side of HOP ID / LinkHop: links and bio pages,
 * the abuse queue (reports and scanner findings), IP bans, the domain
 * blacklist and site settings. Only for admin clients (Imperium).
 */
class HopContentController extends Controller
{
    public function __construct(private readonly LinkService $links) {}

    /** GET /api/hop/manifest: what Imperium can show and do here. */
    public function manifest(): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => [
            'app' => 'linkhop', 'name' => config('app.name'), 'icon' => 'link', 'base_url' => config('app.url'),
            'capabilities' => ['admin_api', 'account_server', 'reports', 'settings', 'search', 'metrics'],
            'content_types' => array_values(array_filter([
                ['type' => 'link', 'label' => 'Links', 'actions' => ['disable', 'enable', 'delete', 'transfer', 'rescan']],
                bio_enabled() ? ['type' => 'bio', 'label' => 'Bio pages', 'actions' => ['unpublish', 'publish', 'delete', 'transfer']] : null,
            ])),
            'extensions' => ['ip', 'domains', 'queue', 'analytics'],
            'settings_schema' => array_map(fn ($k) => ['key' => 'seo.'.$k, 'type' => 'string', 'restart_required' => false], SeoSetting::make()->getFillable()),
        ]]);
    }

    // ---- content -----------------------------------------------------------

    /** GET /api/hop/content?type=link|bio&q=&owner=&status=&cursor=&limit= */
    public function index(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', 'link');
        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        $cursor = (int) $request->query('cursor', 0);
        $q = trim((string) $request->query('q', ''));
        $owner = (string) $request->query('owner', '');
        $status = (string) $request->query('status', '');
        if ($type === 'link') {
            $query = Link::query()->with('user:id,username,name')->orderByDesc('id');
            if ($cursor) {
                $query->where('id', '<', $cursor);
            }
            if ($q !== '') {
                $query->where(fn ($w) => $w->where('slug', 'like', "%{$q}%")->orWhere('target_url', 'like', "%{$q}%"));
            }
            if ($owner === 'guest') {
                $query->whereNull('user_id');
            } elseif ($owner !== '') {
                $query->where('user_id', (int) $owner);
            }
            match ($status) {
                'live' => $query->live(),
                'disabled' => $query->where('status', '!=', Link::STATUS_ACTIVE),
                'expired' => $query->where('status', Link::STATUS_ACTIVE)->ended(),
                'flagged' => $query->whereIn('id', AbuseFlag::where('subject_type', 'link')->where('status', '!=', AbuseFlag::STATUS_DISMISSED)->select('subject_id')),
                default => null,
            };
            $total = (clone $query)->reorder()->count();
            $rows = $query->limit($limit)->get();
            $data = $rows->map(fn (Link $l) => $this->linkRow($l))->all();
        } elseif ($type === 'bio' && bio_enabled()) {
            $query = BioPage::query()->with('user:id,username,name')->orderByDesc('id');
            if ($cursor) {
                $query->where('id', '<', $cursor);
            }
            if ($q !== '') {
                $query->where(fn ($w) => $w->where('slug', 'like', "%{$q}%")->orWhere('title', 'like', "%{$q}%"));
            }
            if ($owner !== '' && $owner !== 'guest') {
                $query->where('user_id', (int) $owner);
            }
            match ($status) {
                'published' => $query->where('is_published', true),
                'draft' => $query->where('is_published', false),
                'adult' => $query->where('is_adult_content', true),
                default => null,
            };
            $total = (clone $query)->reorder()->count();
            $rows = $query->limit($limit)->get();
            $data = $rows->map(fn (BioPage $p) => $this->bioRow($p))->all();
        } else {
            return $this->fail('unknown_type', 'type must be link or bio', 422);
        }
        $next = $rows->count() === $limit ? (string) $rows->last()->id : null;

        return response()->json(['ok' => true, 'data' => $data, 'page' => ['next' => $next, 'total' => $total]]);
    }

    /** GET /api/hop/content/{type}/{id} */
    public function show(string $type, int $id): JsonResponse
    {
        if ($type === 'link') {
            $l = Link::with('user:id,username,name', 'tags:id,name')->find($id);
            if (! $l) {
                return $this->fail('not_found', 'no such link', 404);
            }
            $row = $this->linkRow($l) + [
                'tags' => $l->tags->pluck('name')->all(),
                'password' => (bool) $l->password_hash,
                'redirect_type' => $l->redirect_type,
                'custom_title' => $l->custom_title,
                'safety' => ['status' => $l->safety_status, 'threat' => $l->safety_threat_type, 'scanned_at' => $l->last_safety_scan_at],
                'findings' => $this->flagsFor('link', $l->id),
                'reports' => AbuseReport::where('slug', $l->slug)->latest()->limit(20)->get()->map(fn ($r) => $this->reportRow($r))->all(),
                'clicks_30d' => \DB::table('link_clicks')->where('link_id', $l->id)->where('clicked_at', '>=', now()->subDays(30))->count(),
            ];
        } elseif ($type === 'bio' && bio_enabled()) {
            $p = BioPage::with('user:id,username,name')->find($id);
            if (! $p) {
                return $this->fail('not_found', 'no such bio page', 404);
            }
            $row = $this->bioRow($p) + [
                'blocks' => BioLink::where('bio_page_id', $p->id)->orderBy('position')->limit(100)->get(['id', 'title', 'url', 'type', 'is_active'])->all(),
                'findings' => $this->flagsFor('bio_page', $p->id),
                'theme' => $p->theme,
                'adult' => (bool) $p->is_adult_content,
                'password' => (bool) $p->password_enabled,
            ];
        } else {
            return $this->fail('unknown_type', 'type must be link or bio', 422);
        }

        return response()->json(['ok' => true, 'data' => $row]);
    }

    /** POST /api/hop/content/{type}/{id}/actions {action, reason, params} */
    public function act(Request $request, string $type, int $id): JsonResponse
    {
        $action = (string) $request->input('action', '');
        $params = (array) $request->input('params', []);
        $audit = $this->auditBase($request, $action, $type, (string) $id, $request->input('reason'));
        $result = [];
        if ($type === 'link') {
            $l = Link::find($id);
            if (! $l) {
                return $this->fail('not_found', 'no such link', 404);
            }
            if ($l->user && $l->user->isOwner() && in_array($action, ['disable', 'delete'], true)) {
                return $this->fail('owner_protected', "The owner's links are not moderated through the API.", 403);
            }
            switch ($action) {
                case 'disable':
                    $l->forceFill(['status' => Link::STATUS_INACTIVE])->save();
                    $this->links->forgetCache($l);
                    break;
                case 'enable':
                    $l->forceFill(['status' => Link::STATUS_ACTIVE])->save();
                    $this->links->forgetCache($l);
                    AbuseFlag::where('subject_type', 'link')->where('subject_id', $l->id)->update(['status' => AbuseFlag::STATUS_DISMISSED]);
                    break;
                case 'delete':
                    if ($l->qr_code_path) {
                        Storage::disk('public')->delete($l->qr_code_path);
                    }
                    $this->links->forgetCache($l);
                    $l->tags()->detach();
                    $l->delete();
                    HopAudit::create($audit + ['params' => ['slug' => $l->slug, 'target_url' => $l->target_url]]);

                    return response()->json(['ok' => true, 'data' => ['deleted' => true, 'id' => (string) $id]]);
                case 'transfer':
                    $to = User::find((int) ($params['to'] ?? 0));
                    if (! $to) {
                        return $this->fail('bad_params', 'params.to must be a HOP id', 422);
                    }
                    $l->forceFill(['user_id' => $to->id, 'folder_id' => null])->save();
                    $result = ['to' => (string) $to->id];
                    break;
                case 'rescan':
                    $l->forceFill(['last_safety_scan_at' => null])->save();
                    break;
                default:
                    return $this->fail('unknown_action', 'disable, enable, delete, transfer or rescan', 422);
            }
            HopAudit::create($audit + ['params' => $result ?: $params]);

            return response()->json(['ok' => true, 'data' => $this->linkRow($l->fresh('user'))]);
        }
        if ($type === 'bio' && bio_enabled()) {
            $p = BioPage::find($id);
            if (! $p) {
                return $this->fail('not_found', 'no such bio page', 404);
            }
            if ($p->user && $p->user->isOwner() && in_array($action, ['unpublish', 'delete'], true)) {
                return $this->fail('owner_protected', "The owner's pages are not moderated through the API.", 403);
            }
            switch ($action) {
                case 'unpublish':
                    $p->forceFill(['is_published' => false])->save();
                    AbuseFlag::firstOrCreate(['subject_type' => 'bio_page', 'subject_id' => $p->id, 'action' => AbuseFlag::ACTION_DISABLED, 'status' => AbuseFlag::STATUS_CONFIRMED],
                        ['user_id' => $p->user_id, 'score' => 100, 'signals' => [], 'summary' => 'Unpublished by an admin']);
                    break;
                case 'publish':
                    $p->forceFill(['is_published' => true])->save();
                    AbuseFlag::where('subject_type', 'bio_page')->where('subject_id', $p->id)->update(['status' => AbuseFlag::STATUS_DISMISSED]);
                    break;
                case 'delete':
                    $files = BioUploads::ofPage($p);
                    $p->delete();
                    BioUploads::deleteUnused($files);
                    HopAudit::create($audit + ['params' => ['slug' => $p->slug, 'title' => $p->title]]);

                    return response()->json(['ok' => true, 'data' => ['deleted' => true, 'id' => (string) $id]]);
                case 'transfer':
                    $to = User::find((int) ($params['to'] ?? 0));
                    if (! $to) {
                        return $this->fail('bad_params', 'params.to must be a HOP id', 422);
                    }
                    $p->forceFill(['user_id' => $to->id])->save();
                    $result = ['to' => (string) $to->id];
                    break;
                default:
                    return $this->fail('unknown_action', 'unpublish, publish, delete or transfer', 422);
            }
            HopAudit::create($audit + ['params' => $result ?: $params]);

            return response()->json(['ok' => true, 'data' => $this->bioRow($p->fresh('user'))]);
        }

        return $this->fail('unknown_type', 'type must be link or bio', 422);
    }

    // ---- reports (abuse queue) ---------------------------------------------

    /** GET /api/hop/reports?status=open|closed&cursor=&limit=: people's reports and the scanner's findings, newest first. */
    public function reports(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'open');
        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        $reports = AbuseReport::query()->latest('id')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['open', 'investigating']))
            ->when($status === 'closed', fn ($q) => $q->where('status', 'closed'))
            ->limit($limit)->get()->map(fn ($r) => $this->reportRow($r));
        $flags = AbuseFlag::query()->with('user:id,username')->latest('id')
            ->when($status === 'open', fn ($q) => $q->where('status', AbuseFlag::STATUS_OPEN))
            ->when($status === 'closed', fn ($q) => $q->where('status', '!=', AbuseFlag::STATUS_OPEN))
            ->limit($limit)->get()->map(fn ($f) => $this->flagRow($f));
        $all = $reports->concat($flags)->sortByDesc('created_at')->values()->take($limit)->all();

        return response()->json(['ok' => true, 'data' => $all, 'page' => ['next' => null, 'total' => count($all)]]);
    }

    /** POST /api/hop/reports/{kind}/{id}/actions: report → investigate|close; flag → confirm|restore. */
    public function reportAct(Request $request, string $kind, int $id): JsonResponse
    {
        $action = (string) $request->input('action', '');
        $audit = $this->auditBase($request, $action, $kind, (string) $id, $request->input('reason'));
        if ($kind === 'report') {
            $r = AbuseReport::find($id);
            if (! $r) {
                return $this->fail('not_found', 'no such report', 404);
            }
            $map = ['investigate' => 'investigating', 'close' => 'closed', 'reopen' => 'open', 'dismiss' => 'closed', 'resolve' => 'closed'];
            if (! isset($map[$action])) {
                return $this->fail('unknown_action', 'investigate, close, reopen', 422);
            }
            $r->update(['status' => $map[$action]]);
            HopAudit::create($audit);

            return response()->json(['ok' => true, 'data' => $this->reportRow($r)]);
        }
        if ($kind === 'flag') {
            $f = AbuseFlag::find($id);
            if (! $f) {
                return $this->fail('not_found', 'no such finding', 404);
            }
            if ($action === 'confirm') {
                $f->update(['status' => AbuseFlag::STATUS_CONFIRMED]);
            } elseif ($action === 'restore' || $action === 'dismiss') {
                match ($f->subject_type) {
                    'link' => tap(Link::find($f->subject_id), function (?Link $link) {
                        if ($link) {
                            $link->forceFill(['status' => Link::STATUS_ACTIVE])->save();
                            $this->links->forgetCache($link);
                        }
                    }),
                    'bio_page' => bio_enabled() ? BioPage::whereKey($f->subject_id)->update(['is_published' => true]) : null,
                    'bio_link' => bio_enabled() ? BioLink::whereKey($f->subject_id)->update(['is_active' => true]) : null,
                    'user' => User::whereKey($f->subject_id)->update(['status' => User::STATUS_ACTIVE]),
                    default => null,
                };
                $f->update(['status' => AbuseFlag::STATUS_DISMISSED]);
            } else {
                return $this->fail('unknown_action', 'confirm or restore', 422);
            }
            HopAudit::create($audit);

            return response()->json(['ok' => true, 'data' => $this->flagRow($f->fresh('user'))]);
        }

        return $this->fail('unknown_type', 'kind must be report or flag', 422);
    }

    // ---- extensions: ip, domains, queue -----------------------------------------

    /** GET /api/hop/x/ip */
    public function ip(): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => [
            'bans' => IpBan::latest('id')->limit(500)->get()->map(fn ($b) => ['id' => $b->id, 'ip' => $b->ip_address, 'reason' => $b->reason,
                'expires_at' => $b->expires_at?->toIso8601String(), 'created_at' => $b->created_at?->toIso8601String()])->all(),
            'watchlist' => IpWatchlist::with('user:id,username')->latest('last_attempt_at')->limit(200)->get()->map(fn ($w) => ['id' => $w->id, 'ip' => $w->ip_address,
                'user' => $w->user?->username, 'reason' => $w->reason, 'attempts' => $w->attempt_count, 'last_attempt_at' => $w->last_attempt_at])->all(),
        ]]);
    }

    /** POST /api/hop/x/ip/actions {action: ban|unban|unwatch, params: {ip, reason, expires_at, id}} */
    public function ipAct(Request $request): JsonResponse
    {
        $action = (string) $request->input('action', '');
        $p = (array) $request->input('params', []);
        $audit = $this->auditBase($request, 'ip.'.$action, 'ip', (string) ($p['ip'] ?? $p['id'] ?? ''), $request->input('reason'));
        switch ($action) {
            case 'ban':
                $ip = trim((string) ($p['ip'] ?? ''));
                if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $this->fail('bad_params', 'params.ip must be an IP address', 422);
                }
                $ban = IpBan::updateOrCreate(['ip_address' => $ip], ['reason' => (string) ($p['reason'] ?? $request->input('reason') ?? ''), 'expires_at' => ! empty($p['expires_at']) ? $p['expires_at'] : null]);
                Cache::forget('ip_ban:'.$ip);
                HopAudit::create($audit + ['params' => $p]);

                return response()->json(['ok' => true, 'data' => ['id' => $ban->id, 'ip' => $ip]]);
            case 'unban':
                $n = IpBan::where('ip_address', (string) ($p['ip'] ?? ''))->orWhere('id', (int) ($p['id'] ?? 0))->get()->each(function (IpBan $b) {
                    Cache::forget('ip_ban:'.$b->ip_address);
                    $b->delete();
                })->count();
                HopAudit::create($audit + ['params' => $p]);

                return response()->json(['ok' => true, 'data' => ['deleted' => $n]]);
            case 'unwatch':
                $n = IpWatchlist::where('ip_address', (string) ($p['ip'] ?? ''))->orWhere('id', (int) ($p['id'] ?? 0))->delete();
                HopAudit::create($audit + ['params' => $p]);

                return response()->json(['ok' => true, 'data' => ['deleted' => $n]]);
        }

        return $this->fail('unknown_action', 'ban, unban or unwatch', 422);
    }

    /** GET /api/hop/x/domains */
    public function domains(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $rows = DomainBlacklist::query()->when($q !== '', fn ($w) => $w->where('domain', 'like', "%{$q}%"))->latest('id')->limit(500)->get()
            ->map(fn ($d) => ['id' => $d->id, 'domain' => $d->domain, 'match_type' => $d->match_type, 'category' => $d->category, 'notes' => $d->notes, 'created_at' => $d->created_at?->toIso8601String()])->all();

        return response()->json(['ok' => true, 'data' => $rows, 'page' => ['next' => null, 'total' => DomainBlacklist::count()]]);
    }

    /** POST /api/hop/x/domains/actions {action: add|remove, params: {domain, match_type, category, notes, id}} */
    public function domainsAct(Request $request): JsonResponse
    {
        $action = (string) $request->input('action', '');
        $p = (array) $request->input('params', []);
        $audit = $this->auditBase($request, 'domain.'.$action, 'domain', (string) ($p['domain'] ?? $p['id'] ?? ''), $request->input('reason'));
        if ($action === 'add') {
            $domain = strtolower(trim((string) ($p['domain'] ?? '')));
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = preg_replace('#^www\.#', '', rtrim($domain, '/'));
            if ($domain === '' || ! preg_match('/^[a-z0-9.*-]+\.[a-z]{2,}$/', $domain)) {
                return $this->fail('bad_params', 'params.domain must be a domain', 422);
            }
            $row = DomainBlacklist::updateOrCreate(['domain' => $domain], ['match_type' => in_array($p['match_type'] ?? '', ['exact', 'wildcard'], true) ? $p['match_type'] : 'exact',
                'category' => (string) ($p['category'] ?? 'other'), 'notes' => (string) ($p['notes'] ?? '')]);
            HopAudit::create($audit + ['params' => $p]);

            return response()->json(['ok' => true, 'data' => ['id' => $row->id, 'domain' => $domain]]);
        }
        if ($action === 'remove') {
            $n = DomainBlacklist::where('domain', (string) ($p['domain'] ?? ''))->orWhere('id', (int) ($p['id'] ?? 0))->delete();
            HopAudit::create($audit + ['params' => $p]);

            return response()->json(['ok' => true, 'data' => ['deleted' => $n]]);
        }

        return $this->fail('unknown_action', 'add or remove', 422);
    }

    /** GET /api/hop/x/queue: the job queue's heartbeat and backlog. */
    public function queue(): JsonResponse
    {
        $beat = Cache::get('queue:heartbeat');
        $beatAt = $beat ? Carbon::parse(is_array($beat) ? ($beat['at'] ?? null) : $beat) : null;
        $scan = Cache::get('abuse:scan:last_run');

        return response()->json(['ok' => true, 'data' => [
            'worker' => $beatAt && $beatAt->gt(now()->subMinutes(10)) ? 'ok' : 'stale',
            'heartbeat_at' => $beatAt?->toIso8601String(),
            'pending' => \DB::table('jobs')->count(),
            'failed' => \DB::table('failed_jobs')->count(),
            'abuse_scan_at' => is_array($scan) ? ($scan['at'] ?? null) : null,
        ]]);
    }

    /** GET /api/hop/x/analytics: global LinkHop analytics (countries, devices, referrers, top links). */
    public function analytics(): JsonResponse
    {
        $thirtyDaysAgo = now()->subDays(30);

        $topCountries = LinkClick::query()
            ->where('clicked_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('country')
            ->selectRaw('country, country_name, COUNT(*) as clicks')
            ->groupBy('country', 'country_name')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();

        $topLinks = Link::query()
            ->select('id', 'slug', 'target_url', 'clicks', 'status', 'created_at')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();

        $totalLinks = Link::count();
        $totalClicks = (int) Link::sum('clicks');
        $clicks30d = LinkClick::query()->where('clicked_at', '>=', $thirtyDaysAgo)->count();

        $topReferrers = LinkClick::query()
            ->where('clicked_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('referer')
            ->where('referer', '!=', '')
            ->selectRaw('referer, COUNT(*) as clicks')
            ->groupBy('referer')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();

        return response()->json(['ok' => true, 'data' => [
            'total_links' => $totalLinks,
            'total_clicks' => $totalClicks,
            'clicks_30d' => $clicks30d,
            'countries' => $topCountries,
            'top_links' => $topLinks,
            'referrers' => $topReferrers,
        ]]);
    }

    // ---- settings ---------------------------------------------------------------

    /** GET /api/hop/settings: SEO/site settings (editable) plus read-only facts from .env. */
    public function settings(): JsonResponse
    {
        $seo = SeoSetting::first();
        $rows = [];
        foreach (SeoSetting::make()->getFillable() as $k) {
            $rows[] = ['key' => 'seo.'.$k, 'type' => 'string', 'value' => $seo?->{$k}, 'default' => null, 'description' => 'Site & SEO: '.str_replace('_', ' ', $k), 'restart_required' => false, 'readonly' => false];
        }
        $env = [
            'guest_link_max_days' => config('shortener.guest_link_max_days'), 'guest_link_idle_days' => config('shortener.guest_link_idle_days'),
            'retired_slug_days' => config('shortener.retired_slug_days'), 'bio_slug_min_length' => config('bio.slug_min_length'),
            'abuse.threshold_disable' => config('safety.thresholds.disable'), 'abuse.threshold_suspend_user' => config('safety.thresholds.suspend_user'),
            'abuse.register_per_ip_per_hour' => config('safety.register_per_ip_per_hour'),
            'turnstile' => (bool) config('services.turnstile.site_key'), 'safe_browsing' => (bool) config('safety.providers.safe_browsing_key'),
            'urlhaus' => (bool) config('safety.providers.urlhaus_auth_key'), 'google_login' => (bool) config('services.google.client_id'),
            'session_lifetime_min' => config('session.lifetime'),
        ];
        foreach ($env as $k => $v) {
            $rows[] = ['key' => $k, 'type' => is_bool($v) ? 'bool' : 'string', 'value' => $v, 'readonly' => true, 'description' => '.env / config', 'restart_required' => true];
        }

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    /** PUT /api/hop/settings {"seo.site_title": "…", …} */
    public function saveSettings(Request $request): JsonResponse
    {
        $changes = [];
        foreach ((array) $request->all() as $k => $v) {
            if (str_starts_with($k, 'seo.') && in_array(substr($k, 4), SeoSetting::make()->getFillable(), true)) {
                $changes[substr($k, 4)] = is_null($v) ? null : mb_substr((string) $v, 0, 500);
            }
        }
        if (! $changes) {
            return $this->fail('bad_params', 'nothing editable in the body', 422);
        }
        SeoSetting::updateOrCreate(['id' => 1], $changes);
        Cache::forget('seo.settings');
        HopAudit::create($this->auditBase($request, 'settings.update', 'settings', 'seo', null) + ['params' => $changes]);

        return $this->settings();
    }

    // ---- rows -----------------------------------------------------------------------

    private function linkRow(Link $l): array
    {
        $live = $l->status === Link::STATUS_ACTIVE && (! $l->expires_at || $l->expires_at->isFuture());

        return [
            'id' => (string) $l->id, 'type' => 'link', 'title' => $l->custom_title ?: $l->slug, 'slug' => $l->slug, 'target_url' => $l->target_url,
            'owner' => $l->user ? ['sub' => (string) $l->user->id, 'username' => $l->user->username, 'name' => $l->user->name] : null,
            'status' => $live ? 'live' : ($l->status === Link::STATUS_ACTIVE ? 'expired' : 'disabled'),
            'clicks' => (int) $l->clicks, 'expires_at' => $l->expires_at?->toIso8601String(), 'created_at' => $l->created_at?->toIso8601String(),
            'url' => $l->short_url, 'flags' => array_values(array_filter([$l->password_hash ? 'password' : null, $l->safety_status && $l->safety_status !== 'safe' ? 'safety:'.$l->safety_status : null])),
        ];
    }

    private function bioRow(BioPage $p): array
    {
        return [
            'id' => (string) $p->id, 'type' => 'bio', 'title' => $p->title, 'slug' => $p->slug,
            'owner' => $p->user ? ['sub' => (string) $p->user->id, 'username' => $p->user->username, 'name' => $p->user->name] : null,
            'status' => $p->is_published ? 'published' : 'draft', 'views' => (int) $p->view_count,
            'created_at' => $p->created_at?->toIso8601String(), 'url' => public_url('b/'.$p->slug),
            'flags' => array_values(array_filter([$p->is_adult_content ? 'adult' : null, $p->password_enabled ? 'password' : null, $p->seo_noindex ? 'noindex' : null])),
        ];
    }

    private function reportRow(AbuseReport $r): array
    {
        return ['kind' => 'report', 'id' => (string) $r->id, 'subject' => ['type' => 'link', 'slug' => $r->slug, 'url' => $r->url], 'reason' => $r->reason,
            'reporter' => $r->email, 'status' => $r->status, 'created_at' => $r->created_at?->toIso8601String()];
    }

    private function flagRow(AbuseFlag $f): array
    {
        return ['kind' => 'flag', 'id' => (string) $f->id, 'subject' => ['type' => $f->subject_type, 'id' => (string) $f->subject_id],
            'owner' => $f->user ? ['sub' => (string) $f->user->id, 'username' => $f->user->username] : null,
            'score' => (int) $f->score, 'action' => $f->action, 'summary' => $f->summary, 'status' => $f->status, 'created_at' => $f->created_at?->toIso8601String()];
    }

    private function flagsFor(string $type, int $id): array
    {
        return AbuseFlag::where('subject_type', $type)->where('subject_id', $id)->latest('id')->limit(10)->get()->map(fn ($f) => $this->flagRow($f))->all();
    }

    private function auditBase(Request $request, string $action, string $targetType, string $targetId, mixed $reason): array
    {
        /** @var HopApp $app */
        $app = $request->attributes->get('hop_app');

        return ['actor' => (string) $request->header('X-HOP-Actor', 'system'), 'app_slug' => $app->slug, 'action' => $action,
            'target_type' => $targetType, 'target_id' => $targetId, 'reason' => is_string($reason) ? mb_substr($reason, 0, 500) : null];
    }

    private function fail(string $error, string $message, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $error, 'message' => $message], $status);
    }
}
