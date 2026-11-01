<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            // Destination monitor (links:check-destinations): when it last looked, what the destination answered,
            // how many checks in a row failed, and since when the link counts as broken (null = fine).
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->unsignedTinyInteger('check_failures')->default(0);
            $table->timestamp('broken_since')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropIndex(['last_checked_at']);
            $table->dropColumn(['last_checked_at', 'last_status', 'check_failures', 'broken_since']);
        });
    }
};
