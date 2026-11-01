<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            // null = off; otherwise {mode: auto|custom, ios, android, ios_store, android_store} (App\Support\DeepLinks)
            $table->json('app_links')->nullable()->after('ab_variants');
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn('app_links');
        });
    }
};
