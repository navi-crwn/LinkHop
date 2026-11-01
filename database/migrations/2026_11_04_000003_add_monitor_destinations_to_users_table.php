<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The users table belongs to the hub and is shared with the other apps: add the column only once.
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'monitor_destinations')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Opt-out of the destination monitor (and its email) for all of this person's links.
            $table->boolean('monitor_destinations')->default(true);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'monitor_destinations')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('monitor_destinations');
        });
    }
};
