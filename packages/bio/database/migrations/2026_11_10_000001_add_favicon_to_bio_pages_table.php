<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A favicon of the page's own (an upload under bio-images/), shown in the browser tab instead of LinkHop's. Same column as BioHop. */
    public function up(): void
    {
        if (! Schema::hasTable('bio_pages') || Schema::hasColumn('bio_pages', 'favicon')) {
            return;
        }
        Schema::table('bio_pages', function (Blueprint $table) {
            $table->string('favicon', 500)->nullable()->after('seo_noindex');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('bio_pages') && Schema::hasColumn('bio_pages', 'favicon')) {
            Schema::table('bio_pages', fn (Blueprint $table) => $table->dropColumn('favicon'));
        }
    }
};
