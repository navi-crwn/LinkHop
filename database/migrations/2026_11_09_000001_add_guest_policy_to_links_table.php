<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production already carries safety_status (and possibly last_safety_scan_at) from an earlier safety scan
        // feature whose migration is no longer in the tree, so every column and index is added only when missing.
        Schema::table('links', function (Blueprint $table) {
            if (! Schema::hasColumn('links', 'safety_status')) {
                // Guest policy (config/shortener.php, guest_policy): null = nothing to say (an account's link, or an
                // allowlisted destination); pending = a guest link to an unknown site, shown behind a warning page
                // until abuse:scan has looked at it; safe = cleared by the scan; flagged = the scan disabled it.
                $table->string('safety_status', 20)->nullable()->after('status');
            }
            if (! Schema::hasColumn('links', 'last_safety_scan_at')) {
                $table->timestamp('last_safety_scan_at')->nullable()->after('safety_status');
            }
            if (! Schema::hasColumn('links', 'creator_hash')) {
                // Who made a guest link: a keyed hash of the address (App\Support\VisitorHash), never the address
                // itself. Null for an account's link (user_id says who) and for guest links made before this column.
                $table->char('creator_hash', 40)->nullable()->after('user_id');
            }
        });
        Schema::table('links', function (Blueprint $table) {
            if (! Schema::hasIndex('links', ['safety_status'])) {
                $table->index('safety_status');
            }
            if (! Schema::hasIndex('links', ['creator_hash'])) {
                $table->index('creator_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            if (Schema::hasIndex('links', ['safety_status'])) {
                $table->dropIndex(['safety_status']);
            }
            if (Schema::hasIndex('links', ['creator_hash'])) {
                $table->dropIndex(['creator_hash']);
            }
            $table->dropColumn(array_values(array_filter(
                ['safety_status', 'last_safety_scan_at', 'creator_hash'],
                fn (string $c) => Schema::hasColumn('links', $c),
            )));
        });
    }
};
