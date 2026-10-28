<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public click URLs used the sequential bio_links.id, so every destination
 * could be enumerated via /b/click/1..N. Give each block a random public id.
 * Safe to re-run: the column and index are guarded and only empty rows are filled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bio_links', 'public_id')) {
            Schema::table('bio_links', fn (Blueprint $table) => $table->string('public_id', 16)->nullable()->after('id'));
        }
        DB::table('bio_links')->whereNull('public_id')->orderBy('id')->select('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('bio_links')->where('id', $row->id)->update(['public_id' => Str::random(12)]);
                }
            });
        if (! collect(Schema::getIndexes('bio_links'))->contains(fn ($i) => $i['name'] === 'bio_links_public_id_unique')) {
            Schema::table('bio_links', fn (Blueprint $table) => $table->unique('public_id'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bio_links', 'public_id')) {
            Schema::table('bio_links', function (Blueprint $table) {
                $table->dropUnique('bio_links_public_id_unique');
                $table->dropColumn('public_id');
            });
        }
    }
};
