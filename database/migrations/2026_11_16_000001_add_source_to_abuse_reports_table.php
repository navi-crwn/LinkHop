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

        // Back-fill is approximate and only for reports made before this column existed: a stored address with
        // "/b/" in it is a bio page (https://hel.ink/b/<slug>). New reports carry the right source themselves.
        DB::table('abuse_reports')->where('url', 'like', '%/b/%')->update(['source' => 'biohop']);
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
