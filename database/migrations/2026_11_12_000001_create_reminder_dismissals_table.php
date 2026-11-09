<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| A reminder card on the dashboard (the getting-started checklist, the short-links notice) can be hidden for a week
| with the "Hide" button of the family's hop-ui script. One row per account and card: when it was hidden last.
| user_id points at the hub's shared users table, so it carries no foreign key. Safe to run again.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reminder_dismissals')) {
            return;
        }
        Schema::create('reminder_dismissals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('key', 64);
            $table->timestamp('dismissed_at');
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_dismissals');
    }
};
