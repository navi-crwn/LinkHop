<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HOP ID: one profile and one set of roles for every app in the HOP family,
 * and a registry of those apps instead of per-app config keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The public handle shared by every app (/u/{username}, /@username, bio pages).
            $table->string('username', 30)->nullable()->unique()->after('email');
            $table->string('bio', 300)->nullable()->after('avatar');
            $table->string('website', 200)->nullable()->after('bio');
            $table->string('location', 100)->nullable()->after('website');
            $table->string('locale', 10)->nullable()->after('location');
            $table->string('timezone', 64)->nullable()->after('locale');
            // Show name and photo on content in the apps (PixelHop's show_owner).
            $table->boolean('show_owner')->default(true)->after('timezone');
            // A photo uploaded here; `avatar` keeps the external (Google) picture URL.
            $table->string('avatar_path')->nullable()->after('show_owner');
            // Bumped on every profile change so apps can tell a stale copy from a fresh one.
            $table->unsignedInteger('profile_version')->default(1)->after('avatar_path');
        });

        // Roles replace the single SUPERADMIN_EMAIL: owner, admin, moderator, support,
        // or an app-scoped role (role=admin, app_slug=pixelhop).
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->string('app_slug', 32)->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role', 'app_slug']);
        });

        // The HOP app registry (replaces services.pixelhop / services.notehop).
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 60);
            $table->string('url', 200);
            $table->string('callback_path', 100)->default('/auth/sso/callback');
            $table->string('start_path', 100)->default('/auth/sso');
            // Pages "open" can land on, e.g. {"default": "/dashboard", "new": "/"}.
            $table->json('pages')->nullable();
            // The page to continue to after sign-up/verification (a key of `pages`).
            $table->string('continue_page', 40)->default('default');
            $table->string('menu_label', 40)->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('color', 16)->nullable();
            // active: normal; maintenance: sign-in refused with a message; off: hidden.
            $table->string('status', 16)->default('active');
            // Shared HMAC secret (encrypted at rest), plus the previous one during a rotation.
            $table->text('secret');
            $table->text('secret_prev')->nullable();
            $table->timestamp('secret_prev_until')->nullable();
            // What the app implements: events (webhooks), account_api (own login/register
            // forms), admin_api (HOP App Protocol), metrics.
            $table->json('capabilities')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        // Old usernames stay reserved for a while (and could redirect later).
        Schema::create('username_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username', 30)->index();
            $table->timestamp('released_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('username_history');
        Schema::dropIfExists('apps');
        Schema::dropIfExists('roles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'bio', 'website', 'location', 'locale', 'timezone', 'show_owner', 'avatar_path', 'profile_version']);
        });
    }
};
