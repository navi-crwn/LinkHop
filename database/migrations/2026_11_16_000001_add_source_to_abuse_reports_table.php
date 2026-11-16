<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * abuse_reports is shared: BioHop's /report-abuse form writes to it as well. `source` says which app the report was
 * made about, so Imperium can show a report about a bio page in BioHop's queue instead of LinkHop's.
 * Additive: one column with a default, nothing is deleted, and running it again (or on a table that already has
 * the column) does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('abuse_reports') || Schema::hasColumn('abuse_reports', 'source')) {
            return;
        }

        Schema::table('abuse_reports', function (Blueprint $table) {
            $table->string('source', 20)->default('linkhop')->index();
        });

        // Back-fill, only for reports made before this column existed (new reports carry their own source).
        // BioHop's form stores the public address of the page: https://hel.ink/b/<slug> (or on bio.hel.ink). LinkHop's
        // own form accepts any address, so "/b/" somewhere in a pasted URL proves nothing. A row becomes biohop only
        // when the address starts with one of those two bio addresses and the slug column is empty or is the slug in
        // the address. The check is done here and not in SQL so it behaves the same on SQLite and MySQL/MariaDB.
        DB::table('abuse_reports')
            ->where(fn ($q) => $q->whereRaw('lower(url) like ?', ['https://hel.ink/b/%'])->orWhereRaw('lower(url) like ?', ['https://bio.hel.ink/b/%']))
            ->select(['id', 'url', 'slug'])->orderBy('id')
            ->chunkById(500, function ($rows) {
                $ids = [];
                foreach ($rows as $row) {
                    $slug = self::bioSlug((string) $row->url);
                    if ($slug !== null && ($row->slug === null || $row->slug === '' || strtolower($row->slug) === $slug)) {
                        $ids[] = $row->id;
                    }
                }
                if ($ids) {
                    DB::table('abuse_reports')->whereIn('id', $ids)->update(['source' => 'biohop']);
                }
            });
    }

    /** The slug of https://hel.ink/b/<slug> or https://bio.hel.ink/b/<slug> (lower case), null for any other address. */
    private static function bioSlug(string $url): ?string
    {
        if (! preg_match('~^https://(?:bio\.)?hel\.ink/b/([^/?#]+)~i', $url, $m)) {
            return null;
        }

        return strtolower(rawurldecode($m[1]));
    }

    public function down(): void
    {
        if (! Schema::hasColumn('abuse_reports', 'source')) {
            return;
        }

        Schema::table('abuse_reports', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
