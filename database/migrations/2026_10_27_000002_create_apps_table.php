<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The registry of apps that may call this app's signed admin API (Imperium).
 * Created originally by 2026_10_27_000001_create_hop_id_tables (now in
 * database/migrations-archive with the other account tables, which belong to
 * the hub); this file only adds the table where it does not exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('apps')) {
            return;
        }
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 60);
            $table->string('url', 200);
            $table->string('callback_path', 100)->default('/auth/sso/callback');
            $table->string('start_path', 100)->default('/auth/sso');
            $table->json('pages')->nullable();
            $table->string('continue_page', 40)->default('default');
            $table->string('menu_label', 40)->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('color', 16)->nullable();
            $table->string('status', 16)->default('active');
            $table->text('secret');
            $table->text('secret_prev')->nullable();
            $table->timestamp('secret_prev_until')->nullable();
            $table->json('capabilities')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
