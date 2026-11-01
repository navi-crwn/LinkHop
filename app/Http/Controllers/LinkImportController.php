<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Link;
use App\Services\QuotaService;
use App\Support\LinkImport;
use Hop\Safety\LinkSafety;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Import links from a CSV file: upload, preview with a verdict for every row, then confirm. The checked rows wait
 * on the server (cache, 30 minutes, this account only) between the two steps; nothing leaves the app.
 */
class LinkImportController extends Controller
{
    use HandlesSlugs;

    private const KEEP_MINUTES = 30;

    public function show(Request $request)
    {
        $preview = null;
        if ($token = (string) $request->query('preview')) {
            $rows = Cache::get($this->cacheKey($request, $token));
            if ($rows === null) {
                return redirect()->route('links.import')->withErrors(['file' => __('The preview has expired. Upload the file again.')]);
            }
            $preview = ['token' => $token, 'rows' => $rows, 'counts' => collect($rows)->countBy('status')->all()];
        }

        return view('links-import', [
            'preview' => $preview,
            'result' => session('import_result'),
            'maxRows' => LinkImport::MAX_ROWS,
        ]);
    }

    public function sample()
    {
        return response(LinkImport::sample(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="linkhop-import-sample.csv"',
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:'.(LinkImport::MAX_BYTES / 1024), 'extensions:csv,txt']], [
            'file.required' => __('Choose a CSV file.'),
            'file.max' => __('The file is too large (512 KB at most).'),
            'file.extensions' => __('Choose a file that ends in .csv.'),
        ]);
        @set_time_limit(120);
        $parsed = LinkImport::parse((string) file_get_contents($request->file('file')->getRealPath()));
        if ($parsed['error']) {
            return back()->withErrors(['file' => $parsed['error']]);
        }
        $token = Str::random(32);
        Cache::put($this->cacheKey($request, $token), LinkImport::check($request->user(), $parsed['rows']), now()->addMinutes(self::KEEP_MINUTES));

        return redirect()->route('links.import', ['preview' => $token]);
    }

    public function commit(Request $request)
    {
        $token = (string) $request->input('token');
        $rows = Cache::get($this->cacheKey($request, $token));
        if ($rows === null) {
            return redirect()->route('links.import')->withErrors(['file' => __('The preview has expired. Upload the file again.')]);
        }
        @set_time_limit(120);
        $user = $request->user();
        $withDuplicates = $request->boolean('allow_duplicates');
        $remaining = app(QuotaService::class)->remainingFor($user->id);
        $safety = app(LinkSafety::class);
        $folders = $user->folders()->get()->keyBy(fn ($f) => mb_strtolower($f->name));
        $tags = $user->tags()->get()->keyBy(fn ($t) => mb_strtolower($t->name));
        $defaultFolderId = $folders->first(fn ($f) => $f->is_default)?->id;
        $created = 0;
        $skipped = [];
        foreach ($rows as $row) {
            $reason = match (true) {
                $row['status'] === 'error' => __('Skipped: the row has a problem.'),
                $row['status'] === 'duplicate' && ! $withDuplicates => __('Skipped: duplicate.'),
                $remaining <= 0 => __('Skipped: your link allowance is used up.'),
                default => null,
            };
            if ($reason === null && ($why = $safety->rejectionReason($row['destination'], $row['slug']))) {
                $reason = __($why);
            }
            if ($reason !== null) {
                $skipped[] = ['line' => $row['line'], 'destination' => $row['destination'], 'reason' => $reason];

                continue;
            }
            $folderKey = mb_strtolower($row['folder']);
            if ($row['folder'] !== '' && ! $folders->has($folderKey)) {
                $folders[$folderKey] = $user->folders()->create(['name' => $row['folder'], 'is_default' => false]);
            }
            $tagIds = [];
            foreach ($row['tags'] as $name) {
                if (! $tags->has(mb_strtolower($name))) {
                    $tags[mb_strtolower($name)] = $user->tags()->create(['name' => $name]);
                }
                $tagIds[] = $tags[mb_strtolower($name)]->id;
            }
            try {
                $link = $user->links()->create([
                    'target_url' => $row['destination'],
                    'slug' => $row['slug'] !== '' ? $row['slug'] : $this->generateUniqueSlug(),
                    'status' => Link::STATUS_ACTIVE,
                    'folder_id' => $row['folder'] !== '' ? $folders[$folderKey]->id : $defaultFolderId,
                    'redirect_type' => '302',
                    'content_warning' => 'none',
                    'expires_at' => LinkImport::parseDate($row['expires_at']),
                    'max_clicks' => $row['max_clicks'],
                ]);
            } catch (UniqueConstraintViolationException) {
                // someone took the slug between the preview and now
                $skipped[] = ['line' => $row['line'], 'destination' => $row['destination'], 'reason' => __('Skipped: the slug is already taken.')];

                continue;
            }
            $link->tags()->sync($tagIds);
            $remaining--;
            $created++;
        }
        Cache::forget($this->cacheKey($request, $token));
        if ($created > 0) {
            ActivityLog::log('created', 'Link', null, "Imported {$created} links from a CSV file");
        }

        return redirect()->route('links.import')->with('import_result', ['created' => $created, 'skipped' => $skipped]);
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'linkimport:'.$request->user()->id.':'.preg_replace('/[^A-Za-z0-9]/', '', $token);
    }
}
