<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            // WhatsApp rotator (App\Support\WhatsAppRotator): null = an ordinary link. round_robin or weighted otherwise.
            if (! Schema::hasColumn('links', 'wa_mode')) {
                $table->string('wa_mode', 16)->nullable();
            }
            // The numbers: [{id, number, weight, message}]. The id is stable, link_clicks.variant records it.
            if (! Schema::hasColumn('links', 'wa_numbers')) {
                $table->json('wa_numbers')->nullable();
            }
            // Visits handed out so far. Moved with one UPDATE ... + 1, so concurrent visits never take the same turn.
            if (! Schema::hasColumn('links', 'wa_cursor')) {
                $table->unsignedBigInteger('wa_cursor')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['wa_mode', 'wa_numbers', 'wa_cursor'],
                fn (string $c) => Schema::hasColumn('links', $c),
            )));
        });
    }
};
