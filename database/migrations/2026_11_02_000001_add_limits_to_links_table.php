<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            // Stops working after this many counted clicks (people, not crawlers).
            $table->unsignedInteger('max_clicks')->nullable()->after('expires_at');
            // Does not work before this moment.
            $table->timestamp('starts_at')->nullable()->after('max_clicks');
            // Where visitors go when the link has ended (expired or out of clicks).
            $table->text('fallback_url')->nullable()->after('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn(['max_clicks', 'starts_at', 'fallback_url']);
        });
    }
};
