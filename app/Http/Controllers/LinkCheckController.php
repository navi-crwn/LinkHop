<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Hop\Bio\Models\BioPage;
use Hop\Safety\LinkSafety;
use Illuminate\Http\Request;

/**
 * Public "where does this link go?" page: resolves a hel.ink short link
 * without redirecting or counting a click.
 */
class LinkCheckController extends Controller
{
    public function __invoke(Request $request, LinkSafety $safety)
    {
        $input = trim((string) $request->query('url', ''));
        if ($input === '') {
            return view('link-check', ['input' => '', 'result' => null]);
        }

        return view('link-check', ['input' => $input, 'result' => $this->resolve($input, $safety)]);
    }

    private function resolve(string $input, LinkSafety $safety): array
    {
        // Both the app host (APP_URL) and the public host (PUBLIC_URL) are ours.
        $ownHosts = array_unique([preg_replace('/^www\./', '', strtolower((string) parse_url(config('app.url'), PHP_URL_HOST))), strtolower(public_host())]);
        $path = $input;
        if (preg_match('#^(https?://)?([^/\s]+\.[^/\s]+)(/.*)?$#i', $input, $m)) {
            if (! in_array(preg_replace(['/^www\./', '/:\d+$/'], '', strtolower($m[2])), $ownHosts, true)) {
                return ['type' => 'foreign', 'host' => $m[2]];
            }
            $path = $m[3] ?? '';
        }
        $path = trim(strtok($path, '?#') ?: '', '/');

        if (preg_match('#^b/([A-Za-z0-9]+)$#', $path, $m)) {
            if (! bio_enabled()) {
                return ['type' => 'missing', 'slug' => $path];
            }
            $page = BioPage::where('slug', strtolower($m[1]))->where('is_published', true)->withCount(['links' => fn ($q) => $q->where('is_active', true)])->first();

            return $page && ! $page->user?->isSuspended()
                ? ['type' => 'bio', 'page' => $page]
                : ['type' => 'missing', 'slug' => $path];
        }

        // hel.ink/l/{slug} is the link's form; the old hel.ink/{slug} is still understood.
        $path = preg_replace('#^l/#', '', $path);
        $link = $path !== '' ? Link::where('slug', $path)->first() : null;
        if (! $link) {
            return ['type' => 'missing', 'slug' => $path];
        }
        $state = match (true) {
            $link->user?->isSuspended() => 'suspended',
            $link->status !== Link::STATUS_ACTIVE => 'inactive',
            $link->isExpired() => 'expired',
            default => 'active',
        };
        // A link that no longer redirects keeps its destination private too.
        if ($state !== 'active') {
            return ['type' => 'link', 'link' => $link, 'state' => $state, 'protected' => false, 'hidden' => true];
        }
        // Never reveal where a password-protected link points.
        if ($link->requiresPassword()) {
            return ['type' => 'link', 'link' => $link, 'state' => $state, 'protected' => true];
        }
        $assessment = $safety->assess($link->target_url, implode(' ', array_filter([$link->custom_title, $link->custom_description])), remote: true);
        $blocked = array_values(array_intersect($assessment['signals'], config('safety.block_categories', [])));

        return [
            'type' => 'link',
            'link' => $link,
            'state' => $state,
            'protected' => false,
            'host' => $safety->host($link->target_url),
            'signals' => $assessment['signals'],
            'blocked' => $blocked,
        ];
    }
}
