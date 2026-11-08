<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| What guests made, across days (config/shortener.php, guest_policy): a keyed hash of the connection, the
| destination with its tracking query stripped, the slug, and when. Enough to count a cap per hour and per rolling
| day and to see many connections reaching one destination, and useless for locating a visitor. Rows older than
| guest_policy.retention_days are deleted by links:prune-guest-log.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_link_logs', function (Blueprint $table) {
            $table->id();
            // 40 hex chars, the same keyed hash links.creator_hash holds; never the address itself.
            $table->char('creator_hash', 40);
            // host + path, lower case, no query, no fragment, no trailing slash (see App\Support\GuestDestination).
            $table->string('destination', 500);
            $table->string('slug', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['creator_hash', 'created_at']);
            $table->index(['destination', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_link_logs');
    }
};
