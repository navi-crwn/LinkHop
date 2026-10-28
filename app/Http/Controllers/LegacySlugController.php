<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\RetiredSlug;
use App\Services\LinkService;
use App\Support\ShortLinkMove;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * hel.ink/{slug}, the form short links had before they moved to hel.ink/l/{slug}.
 * A slug that is (or was) a link answers with a permanent redirect to the new
 * form until shortener.legacy_slug_until; anything else is the usual 404. Bio
 * pages never had an alias here: they are only at /b/{slug}.
 */
class LegacySlugController extends Controller
{
    public function __construct(private readonly LinkService $links) {}

    public function __invoke(Request $request, string $slug)
    {
        $known = Cache::remember($this->links->cacheKey($slug), 300, fn () => Link::with(['user'])->where('slug', $slug)->first())
            || RetiredSlug::isBlocked($slug);
        if (! $known || ! ShortLinkMove::active()) {
            return response()->view('errors.link-missing', ['slug' => $slug], 404);
        }

        return redirect()->to(public_url('l/'.$slug), 301)->header('Cache-Control', 'public, max-age=86400');
    }
}
