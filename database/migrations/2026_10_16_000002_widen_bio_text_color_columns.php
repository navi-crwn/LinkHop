<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Theme presets use rgba() text colours such as "rgba(255,255,255,0.85)", longer than 20 characters.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bio_pages', function (Blueprint $table) {
            $table->string('text_color', 50)->default('#000000')->change();
            $table->string('title_color', 50)->default('#000000')->change();
            $table->string('bio_color', 50)->default('#64748b')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bio_pages', function (Blueprint $table) {
            $table->string('text_color', 20)->default('#000000')->change();
            $table->string('title_color', 20)->default('#000000')->change();
            $table->string('bio_color', 20)->default('#64748b')->change();
        });
    }
};
