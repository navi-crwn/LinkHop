<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The global analytics endpoint (/api/hop/x/analytics) filters link_clicks by
 * clicked_at over the last 30 days. Without an index that is a full scan as the
 * table grows. Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('link_clicks') || Schema::hasIndex('link_clicks', 'link_clicks_clicked_at_index')) {
            return;
        }
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->index('clicked_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('link_clicks') && Schema::hasIndex('link_clicks', 'link_clicks_clicked_at_index')) {
            Schema::table('link_clicks', function (Blueprint $table) {
                $table->dropIndex(['clicked_at']);
            });
        }
    }
};
