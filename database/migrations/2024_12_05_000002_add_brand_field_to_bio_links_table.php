<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Filename predates the bio_links create migration; on fresh installs the table
        // does not exist yet. 2026_10_14_000001 re-applies this once it does.
        if (! Schema::hasTable('bio_links')) {
            return;
        }
        Schema::table('bio_links', function (Blueprint $table) {
            if (! Schema::hasColumn('bio_links', 'brand')) {
                $table->string('brand', 50)->nullable()->after('thumbnail_url');
            }
            if (! Schema::hasColumn('bio_links', 'settings')) {
                $table->json('settings')->nullable()->after('brand');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('bio_links')) {
            return;
        }
        Schema::table('bio_links', function (Blueprint $table) {
            if (Schema::hasColumn('bio_links', 'brand')) {
                $table->dropColumn('brand');
            }
            if (Schema::hasColumn('bio_links', 'settings')) {
                $table->dropColumn('settings');
            }
        });
    }
};
