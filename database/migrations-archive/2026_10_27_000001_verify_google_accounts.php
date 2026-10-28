<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Google sign-ups passed email_verified_at to create(), but it is not mass assignable, so
// those accounts were left unverified and the `verified` middleware blocked them from links,
// bio pages and API tokens. A google_id is only stored after Google confirmed the address
// (GoogleAuthController), so these accounts are verified: date them from sign-up.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('google_id')->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Nothing to undo: the accounts were verified all along.
    }
};
