<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The signed admin API for Imperium: an audit trail of every admin action,
 * admin notes on accounts, and apps that are registered but stay out of the
 * user-facing menus (Imperium itself).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hop_audit', function (Blueprint $table) {
            $table->id();
            // The X-HOP-Actor header: a HOP user id, or "system".
            $table->string('actor', 64)->default('system');
            // The app that made the call (X-HOP-App).
            $table->string('app_slug', 32);
            $table->string('action', 40);
            $table->string('target_type', 20)->default('user');
            $table->string('target_id', 64)->index();
            $table->string('reason', 500)->nullable();
            $table->json('params')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('hop_user_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('author', 64)->default('system');
            $table->text('text');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('apps', function (Blueprint $table) {
            // Registered (SSO, signed API) but not shown in the sidebar.
            $table->boolean('hidden')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('apps', fn (Blueprint $table) => $table->dropColumn('hidden'));
        Schema::dropIfExists('hop_user_notes');
        Schema::dropIfExists('hop_audit');
    }
};
