<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            // Guest links: a keyed hash of the claim token the creator's browser holds in a cookie (App\Support\GuestClaim).
            // Never the token itself. Cleared when the link is claimed by an account.
            if (! Schema::hasColumn('links', 'claim_token_hash')) {
                $table->char('claim_token_hash', 64)->nullable();
            }
        });
        Schema::table('links', function (Blueprint $table) {
            if (! Schema::hasIndex('links', ['claim_token_hash'])) {
                $table->index('claim_token_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            if (Schema::hasIndex('links', ['claim_token_hash'])) {
                $table->dropIndex(['claim_token_hash']);
            }
        });
        Schema::table('links', function (Blueprint $table) {
            if (Schema::hasColumn('links', 'claim_token_hash')) {
                $table->dropColumn('claim_token_hash');
            }
        });
    }
};
