<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Analytics filter clicks by block and date range together; the slug already has a unique index.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('bio_clicks', 'bio_clicks_bio_link_id_clicked_at_index')) {
            Schema::table('bio_clicks', fn (Blueprint $table) => $table->index(['bio_link_id', 'clicked_at']));
        }
        if (Schema::hasIndex('bio_pages', 'bio_pages_slug_index')) {
            Schema::table('bio_pages', fn (Blueprint $table) => $table->dropIndex('bio_pages_slug_index'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('bio_pages', 'bio_pages_slug_index')) {
            Schema::table('bio_pages', fn (Blueprint $table) => $table->index('slug'));
        }
        if (Schema::hasIndex('bio_clicks', 'bio_clicks_bio_link_id_clicked_at_index')) {
            Schema::table('bio_clicks', fn (Blueprint $table) => $table->dropIndex('bio_clicks_bio_link_id_clicked_at_index'));
        }
    }
};
