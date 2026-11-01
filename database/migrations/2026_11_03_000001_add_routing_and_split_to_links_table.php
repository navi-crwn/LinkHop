<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            // Up to 20 validated rules (country, device, system, browser, language -> address), first match wins.
            $table->json('routing_rules')->nullable()->after('fallback_url');
            // 2 to 5 weighted addresses; the visitor's keyed hash picks one.
            $table->json('ab_variants')->nullable()->after('routing_rules');
        });
        Schema::table('link_clicks', function (Blueprint $table) {
            // Position (1-based) of the split-test variant this visit was sent to; null when there was no split.
            $table->unsignedTinyInteger('variant')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropColumn('variant');
        });
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn(['routing_rules', 'ab_variants']);
        });
    }
};
