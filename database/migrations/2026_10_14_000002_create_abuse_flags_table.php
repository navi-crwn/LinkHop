<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abuse_flags', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20); // link | bio_page | bio_link | user
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->json('signals')->nullable();
            $table->string('action', 20)->default('flagged'); // flagged | disabled | suspended | purged
            $table->string('status', 20)->default('open'); // open | confirmed | dismissed
            $table->string('summary', 255)->nullable(); // domain/slug only, never full CSAM URLs
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abuse_flags');
    }
};
