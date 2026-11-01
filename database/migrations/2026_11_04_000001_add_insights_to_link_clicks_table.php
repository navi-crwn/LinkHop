<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            // Keyed hash of IP + user agent, rotating every day (VisitorHash::daily): counts unique visitors, locates no one.
            $table->string('visitor_hash', 40)->nullable()->after('ip_address');
            // How the visitor reached the link: direct, qr, nfc (?src= on the short address).
            $table->string('source', 12)->default('direct')->after('visitor_hash');
            // Name of the app whose built-in browser opened the link (Instagram, WeChat, ...).
            $table->string('in_app', 32)->nullable()->after('source');
            $table->index(['link_id', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropIndex(['link_id', 'visitor_hash']);
            $table->dropColumn(['visitor_hash', 'source', 'in_app']);
        });
    }
};
