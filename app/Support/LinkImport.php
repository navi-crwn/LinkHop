<?php

namespace App\Support;

use App\Models\Link;
use App\Models\RetiredSlug;
use App\Models\User;
use App\Services\QuotaService;
use Hop\Safety\LinkSafety;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * CSV import of links: reads the file, checks every row with the rules of the link form (and the same safety and
 * quota checks), and hands back what would be created. Nothing is written here; LinkImportController does that
 * after the owner has seen the preview.
 *
 * Columns (first row names them, any order, extra columns are ignored): destination (required), slug, tags
 * (separated by , ; or |), folder, expires_at, max_clicks.
 */
class LinkImport
{
    public const MAX_ROWS = 500;

    public const MAX_BYTES = 524288;

    public const MAX_TAGS_PER_ROW = 10;

    private const ALIASES = [
        'destination' => ['destination', 'url', 'target_url', 'target', 'long_url', 'link'],
        'slug' => ['slug', 'alias', 'custom_slug', 'short'],
        'tags' => ['tags', 'tag'],
        'folder' => ['folder'],
        'expires_at' => ['expires_at', 'expires', 'expiry', 'expiration'],
        'max_clicks' => ['max_clicks', 'click_limit', 'clicks_limit', 'limit'],
    ];

    public static function sample(): string
    {
        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['destination', 'slug', 'tags', 'folder', 'expires_at', 'max_clicks'], ',', '"', '');
        fputcsv($out, ['https://example.com/spring-sale', 'spring-sale', 'promo;email', 'Campaigns', '2027-03-31 23:59', '500'], ',', '"', '');
        fputcsv($out, ['https://example.com/docs/getting-started', '', 'docs', '', '', ''], ',', '"', '');
        fputcsv($out, ['https://example.org/newsletter', 'launch-notes', '', 'Campaigns', '', ''], ',', '"', '');
        rewind($out);

        return (string) stream_get_contents($out);
    }

    /**
     * @return array{rows: list<array<string, string>>, error: string|null}
     */
    public static function parse(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        if (! mb_check_encoding($csv, 'UTF-8')) {
            $csv = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252'); // what Excel writes
        }
        if (trim($csv) === '') {
            return ['rows' => [], 'error' => __('The file is empty.')];
        }
        $firstLine = strtok($csv, "\r\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $csv);
        rewind($handle);
        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        $columns = [];
        foreach ((array) $header as $i => $name) {
            $name = str_replace([' ', '-'], '_', strtolower(trim((string) $name)));
            foreach (self::ALIASES as $field => $aliases) {
                if (in_array($name, $aliases, true) && ! in_array($field, $columns, true)) {
                    $columns[$i] = $field;
                }
            }
        }
        if (! in_array('destination', $columns, true)) {
            return ['rows' => [], 'error' => __('The first row has to name the columns, and one of them has to be "destination". Download the sample file to see the layout.')];
        }
        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = ['line' => (string) $line, 'destination' => '', 'slug' => '', 'tags' => '', 'folder' => '', 'expires_at' => '', 'max_clicks' => ''];
            foreach ($columns as $i => $field) {
                $row[$field] = trim((string) ($cells[$i] ?? ''));
            }
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS) {
                return ['rows' => [], 'error' => __('The file has more than :max rows. Split it into smaller files.', ['max' => self::MAX_ROWS])];
            }
        }
        fclose($handle);
        if ($rows === []) {
            return ['rows' => [], 'error' => __('The file has a header row but no links.')];
        }

        return ['rows' => $rows, 'error' => null];
    }

    /**
     * Check every row. Each result has the cleaned values, a status (ok, duplicate, error, quota) and messages.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function check(User $user, array $rows): array
    {
        $safety = app(LinkSafety::class);
        $remaining = app(QuotaService::class)->remainingFor($user->id);
        $mine = $user->links()->pluck('target_url')->mapWithKeys(fn ($u) => [Link::normalizeUrl($u) => true])->all();
        $wanted = collect($rows)->pluck('slug')->filter()->map(fn ($s) => strtolower($s))->unique()->values()->all();
        $taken = $wanted === [] ? [] : array_flip(Link::whereIn('slug', $wanted)->pluck('slug')->map(fn ($s) => strtolower($s))->all());
        $seenUrls = [];
        $seenSlugs = [];
        $out = [];
        foreach ($rows as $row) {
            $problems = [];
            $url = $row['destination'];
            if ($url !== '' && ! preg_match('/^https?:\/\//i', $url)) {
                $url = LinkRouting::address($url);
            }
            $slug = strtolower($row['slug']);
            $tags = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;|]/', $row['tags']) ?: []), fn ($t) => $t !== '')));
            $max = $row['max_clicks'] === '' ? null : $row['max_clicks'];
            $validator = Validator::make(
                ['target_url' => $url, 'slug' => $slug === '' ? null : $slug, 'expires_at' => $row['expires_at'] ?: null, 'max_clicks' => $max, 'folder' => $row['folder'] ?: null],
                [
                    'target_url' => ['required', 'string', 'max:2000', 'url'],
                    'slug' => ['nullable', 'regex:/^[a-z0-9-]+$/i', 'min:3', 'max:48'],
                    'expires_at' => ['nullable', 'date', 'after:now'],
                    'max_clicks' => LinkLimits::rules()['max_clicks'],
                    'folder' => ['nullable', 'string', 'max:120'],
                ],
                [
                    'target_url.required' => __('The destination is missing.'),
                    'target_url.max' => __('The destination is too long (2000 characters at most).'),
                    'target_url.url' => __('The destination is not a valid web address.'),
                    'slug.regex' => __('Slug can only contain letters, numbers, and hyphens.'),
                    'slug.min' => __('The slug needs at least 3 characters.'),
                    'slug.max' => __('The slug can have 48 characters at most.'),
                    'expires_at.date' => __('The expiry date is not a date. Use a form like 2027-03-31 23:59.'),
                    'expires_at.after' => __('The expiry date has to be in the future.'),
                    'max_clicks.integer' => __('The click limit has to be a whole number.'),
                    'max_clicks.min' => __('The click limit has to be at least 1.'),
                    'max_clicks.max' => __('The click limit is too large.'),
                    'folder.max' => __('The folder name is too long (120 characters at most).'),
                ],
            );
            foreach ($validator->errors()->all() as $message) {
                $problems[] = $message;
            }
            if ($slug !== '' && ! $validator->errors()->has('slug')) {
                if (ReservedSlugs::isReserved($slug)) {
                    $problems[] = __('This slug is reserved.');
                } elseif (isset($taken[$slug]) || RetiredSlug::isBlocked($slug)) {
                    $problems[] = __('The slug is already taken.');
                } elseif (isset($seenSlugs[$slug])) {
                    $problems[] = __('The slug is used twice in this file (first on row :line).', ['line' => $seenSlugs[$slug]]);
                }
            }
            if ($url !== '' && ! $validator->errors()->has('target_url') && ! $problems) {
                $reason = $safety->rejectionReason($url, $slug);
                if ($reason) {
                    $problems[] = __($reason);
                }
            }
            if (count($tags) > self::MAX_TAGS_PER_ROW || collect($tags)->contains(fn ($t) => mb_strlen($t) > 60)) {
                $problems[] = __('A row can have up to :max tags of 60 characters each.', ['max' => self::MAX_TAGS_PER_ROW]);
            }
            $status = $problems ? 'error' : 'ok';
            if ($status === 'ok') {
                $key = Link::normalizeUrl($url);
                if (isset($mine[$key])) {
                    $status = 'duplicate';
                    $problems[] = __('You already have a link to this address.');
                } elseif (isset($seenUrls[$key])) {
                    $status = 'duplicate';
                    $problems[] = __('The same address is on row :line.', ['line' => $seenUrls[$key]]);
                }
                $seenUrls[$key] ??= $row['line'];
            }
            if ($slug !== '' && $status !== 'error') {
                $seenSlugs[$slug] ??= $row['line'];
            }
            if ($status === 'ok') {
                if ($remaining <= 0) {
                    $status = 'quota';
                    $problems[] = __('Your link allowance is used up, so this row would not be created.');
                } else {
                    $remaining--;
                }
            }
            $out[] = [
                'line' => (int) $row['line'], 'destination' => $url, 'slug' => $slug, 'tags' => $tags, 'folder' => $row['folder'],
                'expires_at' => $row['expires_at'], 'max_clicks' => $max === null || $validator->errors()->has('max_clicks') ? null : (int) $max,
                'status' => $status, 'problems' => $problems,
            ];
        }

        return $out;
    }

    public static function parseDate(string $value): ?Carbon
    {
        try {
            return $value === '' ? null : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
