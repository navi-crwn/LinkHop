<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            // Guest policy (config/shortener.php, guest_policy): null = nothing to say (an account's link, or an
            // allowlisted destination); pending = a guest link to an unknown site, shown behind a warning page until
            // abuse:scan has looked at it; safe = cleared by the scan; flagged = the scan disabled it.
            $table->string('safety_status', 20)->nullable()->index()->after('status');
            $table->timestamp('last_safety_scan_at')->nullable()->after('safety_status');
            // Who made a guest link: a keyed hash of the address (App\Support\VisitorHash), never the address itself.
            // Null for an account's link (user_id says who) and for guest links made before this column existed.
            $table->char('creator_hash', 40)->nullable()->index()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropIndex(['safety_status']);
            $table->dropIndex(['creator_hash']);
            $table->dropColumn(['safety_status', 'last_safety_scan_at', 'creator_hash']);
        });
    }
};
