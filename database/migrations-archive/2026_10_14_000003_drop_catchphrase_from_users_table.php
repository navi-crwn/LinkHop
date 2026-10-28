<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catchphrase password recovery was removed (low-entropy secret); passwords
 * are reset by email link only. Drop the stored phrases.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'catchphrase')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('catchphrase'));
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('catchphrase')->nullable());
    }
};
